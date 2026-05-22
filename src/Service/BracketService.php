<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Enum\BracketSize;
use App\Enum\TournamentStatus;
use App\Enum\TournamentVisibility;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Service\Pairing\PlayerStandings;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for generating single elimination brackets.
 *
 * Implements standard TCG bracket generation:
 * - Seeds players by Swiss standings (or registration order if no Swiss)
 * - Uses standard bracket seeding (1 vs N, 2 vs N-1, etc.)
 * - Handles BYEs for non-power-of-2 player counts
 *
 * Seeding Pattern (for 8-player bracket):
 * - Match 1: Seed 1 vs Seed 8
 * - Match 2: Seed 4 vs Seed 5
 * - Match 3: Seed 2 vs Seed 7
 * - Match 4: Seed 3 vs Seed 6
 *
 * This ensures highest seeds meet in later rounds.
 */
class BracketService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PairingService $pairingService,
    ) {
    }

    /**
     * Generate the first round of elimination bracket.
     *
     * @param Tournament  $tournament   The tournament
     * @param BracketSize $bracketSize  Size of the bracket (4, 8, 16, 32)
     * @param int|null    $roundNumber  Optional round number (auto-calculated if null)
     *
     * @return Round The created bracket round
     *
     * @throws InvalidTournamentStateException If tournament is not ONGOING
     * @throws InsufficientPlayersException    If not enough players for bracket
     */
    public function generateBracketFirstRound(
        Tournament $tournament,
        BracketSize $bracketSize,
        ?int $roundNumber = null
    ): Round {
        $this->validateTournamentCanStartBracket($tournament);

        // Get standings to determine seeding
        $standings = $this->pairingService->calculateStandings($tournament);

        // Filter out dropped players - they cannot qualify for top cut
        $activeStandings = array_filter(
            $standings,
            fn (PlayerStandings $standing): bool => !$standing->getRegistration()->isDropped()
        );

        // Sort by standings (match points desc, OMWP desc)
        $sortedStandings = $this->sortStandings($activeStandings);

        // Take top X players based on bracket size
        $topPlayers = array_slice($sortedStandings, 0, $bracketSize->value);

        if (count($topPlayers) < $bracketSize->getMinimumPlayers()) {
            throw new InsufficientPlayersException(
                count($topPlayers),
                $bracketSize->getMinimumPlayers()
            );
        }

        // Calculate round number if not provided
        if ($roundNumber === null) {
            $latestRound = $tournament->getLatestRound();
            $roundNumber = $latestRound !== null ? $latestRound->getRoundNumber() + 1 : 1;
        }

        // Create the bracket round
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber($roundNumber);
        $round->setIsEliminationRound(true);
        $tournament->addRound($round);

        // Generate bracket pairings
        $pairings = $this->generateBracketPairings($topPlayers, $bracketSize);

        // Create matches
        $tableNumber = 1;
        foreach ($pairings as $pairing) {
            if ($pairing['isBye']) {
                $byeMatch = $this->createByeMatch($round, $pairing['player1'], $tableNumber, $pairing['bracketPosition']);
                $round->addMatch($byeMatch);
            } else {
                $match = $this->createBracketMatch(
                    $round,
                    $pairing['player1'],
                    $pairing['player2'],
                    $tableNumber,
                    $pairing['bracketPosition']
                );
                $round->addMatch($match);
            }
            $tableNumber++;
        }

        // Start the round
        $round->start();

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        return $round;
    }

    /**
     * Generate bracket pairings using standard TCG seeding.
     *
     * Standard bracket seeding ensures:
     * - Seed 1 and Seed 2 can only meet in the final
     * - Higher seeds play lower seeds in early rounds
     *
     * @param PlayerStandings[] $players     Sorted players (best first)
     * @param BracketSize       $bracketSize Target bracket size
     *
     * @return array<int, array{player1: Registration, player2?: Registration, isBye: bool, bracketPosition: int}>
     */
    private function generateBracketPairings(array $players, BracketSize $bracketSize): array
    {
        $size = $bracketSize->value;
        $playerCount = count($players);

        // Pad with nulls for BYEs if needed
        $seededPlayers = array_map(
            fn ($s) => $s->getRegistration(),
            $players
        );

        while (count($seededPlayers) < $size) {
            $seededPlayers[] = null; // BYE
        }

        // Generate standard bracket positions
        $bracketOrder = $this->generateBracketOrder($size);

        $pairings = [];
        for ($i = 0; $i < count($bracketOrder); $i += 2) {
            $seed1 = $bracketOrder[$i] - 1;
            $seed2 = $bracketOrder[$i + 1] - 1;

            $player1 = $seededPlayers[$seed1] ?? null;
            $player2 = $seededPlayers[$seed2] ?? null;

            $bracketPosition = (int) ($i / 2) + 1;

            if ($player1 === null && $player2 === null) {
                // Both are BYEs - skip this pairing
                continue;
            }

            if ($player2 === null) {
                // Player 2 is BYE - player 1 advances
                $pairings[] = [
                    'player1' => $player1,
                    'isBye' => true,
                    'bracketPosition' => $bracketPosition,
                ];
            } elseif ($player1 === null) {
                // Player 1 is BYE - player 2 advances
                $pairings[] = [
                    'player1' => $player2,
                    'isBye' => true,
                    'bracketPosition' => $bracketPosition,
                ];
            } else {
                // Regular match
                $pairings[] = [
                    'player1' => $player1,
                    'player2' => $player2,
                    'isBye' => false,
                    'bracketPosition' => $bracketPosition,
                ];
            }
        }

        return $pairings;
    }

    /**
     * Generate standard bracket order for seeding.
     *
     * For an 8-player bracket, this returns: [1, 8, 4, 5, 2, 7, 3, 6]
     * This ensures:
     * - Match 1: Seed 1 vs Seed 8
     * - Match 2: Seed 4 vs Seed 5
     * - Match 3: Seed 2 vs Seed 7
     * - Match 4: Seed 3 vs Seed 6
     *
     * @param int $size Bracket size (must be power of 2)
     *
     * @return int[] Array of seed numbers in bracket order
     */
    private function generateBracketOrder(int $size): array
    {
        if ($size === 2) {
            return [1, 2];
        }

        // Recursively build bracket order
        $halfOrder = $this->generateBracketOrder($size / 2);

        $result = [];
        foreach ($halfOrder as $seed) {
            $result[] = $seed;
            $result[] = $size - $seed + 1;
        }

        return $result;
    }

    /**
     * Sort standings by match points (desc) then OMWP (desc).
     *
     * @param array<int, PlayerStandings> $standings
     *
     * @return PlayerStandings[]
     */
    private function sortStandings(array $standings): array
    {
        $sorted = array_values($standings);
        usort($sorted, function (PlayerStandings $a, PlayerStandings $b): int {
            $pointsDiff = $b->getMatchPoints() <=> $a->getMatchPoints();
            if ($pointsDiff !== 0) {
                return $pointsDiff;
            }

            return $b->getOpponentMatchWinPercentage() <=> $a->getOpponentMatchWinPercentage();
        });

        return $sorted;
    }

    /**
     * Create a bracket match with position.
     */
    private function createBracketMatch(
        Round $round,
        Registration $player1,
        Registration $player2,
        int $tableNumber,
        int $bracketPosition
    ): TournamentMatch {
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber($tableNumber);

        return $match;
    }

    /**
     * Create a BYE match for bracket advancement.
     */
    private function createByeMatch(
        Round $round,
        Registration $player,
        int $tableNumber,
        int $bracketPosition
    ): TournamentMatch {
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player);
        $match->setTableNumber($tableNumber);
        $match->assignBye();

        return $match;
    }

    /**
     * Validate tournament can start elimination bracket.
     *
     * @throws InvalidTournamentStateException
     */
    private function validateTournamentCanStartBracket(Tournament $tournament): void
    {
        if (!$tournament->isOngoing()) {
            throw new InvalidTournamentStateException(
                $tournament->getStatus(),
                [TournamentStatus::ONGOING],
                'demarrer la phase eliminatoire'
            );
        }
    }

    /**
     * Generate the next elimination round from completed matches.
     *
     * @param Tournament $tournament The tournament
     * @param Round      $previousRound The completed bracket round
     *
     * @return Round The next bracket round
     */
    public function generateNextBracketRound(Tournament $tournament, Round $previousRound): Round
    {
        $this->validateTournamentCanStartBracket($tournament);

        if (!$previousRound->areAllMatchesCompleted()) {
            throw new \LogicException('La ronde precedente n\'est pas terminee.');
        }

        // Complete the previous round if not already
        if (!$previousRound->isCompleted()) {
            $previousRound->complete();
        }

        // Get winners from previous round
        $winners = [];
        foreach ($previousRound->getMatches() as $match) {
            $winner = $match->getWinner();
            if ($winner !== null) {
                $winners[] = $winner;
            }
        }

        if (count($winners) < 2) {
            throw new \LogicException('Pas assez de vainqueurs pour generer la prochaine ronde.');
        }

        // Create next round
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber($previousRound->getRoundNumber() + 1);
        $round->setIsEliminationRound(true);
        $tournament->addRound($round);

        // Pair winners (they should already be in bracket order)
        $tableNumber = 1;
        for ($i = 0; $i < count($winners); $i += 2) {
            $match = new TournamentMatch();
            $match->setRound($round);
            $match->setPlayer1($winners[$i]);

            if (isset($winners[$i + 1])) {
                $match->setPlayer2($winners[$i + 1]);
            } else {
                // Odd number - BYE
                $match->assignBye();
            }

            $match->setTableNumber($tableNumber);
            $round->addMatch($match);
            $tableNumber++;
        }

        // Start the round
        $round->start();

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        return $round;
    }

    /**
     * Get the recommended bracket size based on player count.
     */
    public function getRecommendedBracketSize(int $playerCount): BracketSize
    {
        if ($playerCount >= 32) {
            return BracketSize::TOP_32;
        }
        if ($playerCount >= 16) {
            return BracketSize::TOP_16;
        }
        if ($playerCount >= 8) {
            return BracketSize::TOP_8;
        }

        return BracketSize::TOP_4;
    }

    /**
     * Check if the bracket is complete (final match finished).
     *
     * The bracket is complete when:
     * - The latest round has exactly 1 match (the final)
     * - That match is completed
     */
    public function isBracketComplete(Tournament $tournament): bool
    {
        $latestRound = $tournament->getLatestRound();

        if ($latestRound === null) {
            return false;
        }

        $matches = $latestRound->getMatches();

        // Final round has exactly 1 match
        if ($matches->count() !== 1) {
            return false;
        }

        // Check if the final match is completed
        return $matches->first()->isCompleted();
    }

    /**
     * Check if the current bracket round is ready to advance.
     *
     * @return bool True if all matches are complete and more than 1 winner exists
     */
    public function canAdvanceBracket(Round $round): bool
    {
        if (!$round->areAllMatchesCompleted()) {
            return false;
        }

        // Count winners
        $winnerCount = 0;
        foreach ($round->getMatches() as $match) {
            if ($match->getWinner() !== null) {
                $winnerCount++;
            }
        }

        // Need at least 2 winners to have another round
        return $winnerCount >= 2;
    }

    /**
     * Complete a tournament after the final match.
     *
     * This method:
     * 1. Validates the bracket is complete
     * 2. Records final standings
     * 3. Changes tournament status to COMPLETED
     * 4. Auto-publishes results for PUBLIC tournaments (FR56)
     *
     * @return array<int, array{rank: int, registration: Registration}> Final standings
     *
     * @throws \LogicException If bracket is not complete
     */
    public function completeTournament(Tournament $tournament): array
    {
        if (!$this->isBracketComplete($tournament)) {
            throw new \LogicException('Le bracket n\'est pas encore termine.');
        }

        // Get final standings from bracket results
        $standings = $this->calculateFinalStandings($tournament);

        // Complete the tournament
        $tournament->setStatus(TournamentStatus::COMPLETED);
        $tournament->setCompletedAt(new \DateTimeImmutable());

        // FR56: Auto-publish results for PUBLIC tournaments
        if ($tournament->getVisibility() === TournamentVisibility::PUBLIC) {
            $tournament->publishResults();
        }

        $this->entityManager->flush();

        return $standings;
    }

    /**
     * Calculate final standings from bracket results.
     *
     * Standings are determined by:
     * - 1st: Final winner
     * - 2nd: Final loser
     * - 3rd-4th: Semi-final losers
     * - 5th-8th: Quarter-final losers
     * - etc.
     *
     * @return array<int, array{rank: int, registration: Registration}>
     */
    public function calculateFinalStandings(Tournament $tournament): array
    {
        $standings = [];
        $rounds = $tournament->getRounds()->toArray();

        // Process rounds in reverse order (final first)
        $sortedRounds = array_reverse($rounds);
        $currentRank = 1;

        foreach ($sortedRounds as $round) {
            $winners = [];
            $losers = [];

            foreach ($round->getMatches() as $match) {
                if (!$match->isCompleted()) {
                    continue;
                }

                $winner = $match->getWinner();
                $loser = $match->getLoser();

                if ($winner !== null) {
                    // Only add winner from final round
                    if ($currentRank === 1) {
                        $winners[] = $winner;
                    }
                }

                if ($loser !== null) {
                    $losers[] = $loser;
                }
            }

            // Add winners (only from final)
            foreach ($winners as $winner) {
                $standings[] = [
                    'rank' => $currentRank,
                    'registration' => $winner,
                ];
                $currentRank++;
            }

            // Add losers from this round
            foreach ($losers as $loser) {
                $standings[] = [
                    'rank' => $currentRank,
                    'registration' => $loser,
                ];
            }

            // Move to next rank tier
            if (!empty($losers)) {
                $currentRank += count($losers);
            }
        }

        return $standings;
    }

    /**
     * Get bracket progress information.
     *
     * @return array{currentRound: int, totalRounds: int, matchesComplete: int, matchesTotal: int, isComplete: bool}
     */
    public function getBracketProgress(Tournament $tournament): array
    {
        $latestRound = $tournament->getLatestRound();

        if ($latestRound === null) {
            return [
                'currentRound' => 0,
                'totalRounds' => 0,
                'matchesComplete' => 0,
                'matchesTotal' => 0,
                'isComplete' => false,
            ];
        }

        $matchesComplete = $latestRound->getCompletedMatchesCount();
        $matchesTotal = $latestRound->getMatches()->count();

        return [
            'currentRound' => $latestRound->getRoundNumber(),
            'totalRounds' => $tournament->getRoundsCount(),
            'matchesComplete' => $matchesComplete,
            'matchesTotal' => $matchesTotal,
            'isComplete' => $this->isBracketComplete($tournament),
        ];
    }
}
