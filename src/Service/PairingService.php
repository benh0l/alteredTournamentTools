<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Enum\PairingMode;
use App\Enum\TournamentStatus;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Exception\PairingException;
use App\Exception\RoundNotCompleteException;
use App\Repository\TournamentMatchRepository;
use App\Service\Pairing\PlayerStandings;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service responsible for generating pairings for tournament rounds.
 *
 * This service implements the Swiss pairing algorithm as used in TCG tournaments:
 * - Round 1: Random or registration order pairing
 * - Subsequent rounds: Pair by match points, avoiding rematches
 *
 * Supports BYE handling for odd player counts.
 *
 * @see https://en.wikipedia.org/wiki/Swiss-system_tournament
 */
class PairingService
{
    private const MINIMUM_PLAYERS = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TournamentMatchRepository $matchRepository,
    ) {
    }

    /**
     * Generate pairings for Round 1 of a tournament.
     *
     * This method:
     * 1. Validates the tournament can start (PUBLISHED status, enough players)
     * 2. Creates a new Round entity with roundNumber=1
     * 3. Retrieves all registrations
     * 4. If odd number of players, assigns one player a BYE
     * 5. Pairs players according to the specified mode
     * 6. Creates Match entities for all pairings
     * 7. Updates tournament status to ONGOING
     *
     * @param Tournament  $tournament  The tournament to start
     * @param PairingMode $mode        How to pair players (random or by registration order)
     *
     * @return Round The created round with all matches
     *
     * @throws InsufficientPlayersException    If not enough players are registered
     * @throws InvalidTournamentStateException If tournament is not in PUBLISHED status
     */
    public function generateRound1Pairings(Tournament $tournament, PairingMode $mode = PairingMode::RANDOM): Round
    {
        $this->validateTournamentCanStart($tournament);

        $registrations = $tournament->getRegistrations()->toArray();
        $playerCount = count($registrations);

        if ($playerCount < self::MINIMUM_PLAYERS) {
            throw new InsufficientPlayersException($playerCount, self::MINIMUM_PLAYERS);
        }

        // Order registrations based on pairing mode
        $orderedRegistrations = $this->orderRegistrations($registrations, $mode);

        // Create Round 1
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $tournament->addRound($round);

        // Handle BYE if odd number of players
        $byePlayer = null;
        if (count($orderedRegistrations) % 2 !== 0) {
            $byePlayer = $this->selectByePlayer($orderedRegistrations);
            $orderedRegistrations = array_values(array_filter(
                $orderedRegistrations,
                fn (Registration $r): bool => $r !== $byePlayer
            ));
        }

        // Create matches
        $tableNumber = 1;
        for ($i = 0; $i < count($orderedRegistrations); $i += 2) {
            $match = $this->createMatch(
                $round,
                $orderedRegistrations[$i],
                $orderedRegistrations[$i + 1],
                $tableNumber
            );
            $round->addMatch($match);
            $tableNumber++;
        }

        // Create BYE match if needed
        if ($byePlayer !== null) {
            $byeMatch = $this->createByeMatch($round, $byePlayer, $tableNumber);
            $round->addMatch($byeMatch);
        }

        // Update tournament status
        $tournament->setStatus(TournamentStatus::ONGOING);
        $tournament->setStartedAt(new \DateTimeImmutable());

        // Start the round
        $round->start();

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        return $round;
    }

    /**
     * Generate pairings for subsequent rounds (Round 2+) of a tournament.
     *
     * This method implements the Swiss pairing algorithm:
     * 1. Validates the previous round is complete
     * 2. Calculates current standings (match points, tiebreakers)
     * 3. Groups players by match points
     * 4. Pairs players within each group, avoiding rematches
     * 5. Handles odd player groups by pairing down
     * 6. Assigns BYE to lowest-ranked player who hasn't had one
     *
     * @param Tournament $tournament The tournament to continue
     *
     * @return Round The created round with all matches
     *
     * @throws InvalidTournamentStateException If tournament is not ONGOING
     * @throws RoundNotCompleteException       If previous round is not complete
     * @throws PairingException                If valid pairings cannot be generated
     */
    public function generateSubsequentRoundPairings(Tournament $tournament): Round
    {
        $this->validateTournamentCanContinue($tournament);

        $previousRound = $tournament->getLatestRound();
        if ($previousRound === null) {
            throw new InvalidTournamentStateException(
                $tournament->getStatus(),
                [TournamentStatus::PUBLISHED],
                'generer la prochaine ronde (aucune ronde precedente)'
            );
        }

        $this->validateRoundComplete($previousRound);

        // Complete the previous round
        if (!$previousRound->isCompleted()) {
            $previousRound->complete();
        }

        // Calculate current standings
        $standings = $this->calculateStandings($tournament);

        // Create the new round
        $newRoundNumber = $previousRound->getRoundNumber() + 1;
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber($newRoundNumber);
        $tournament->addRound($round);

        // Generate pairings using Swiss algorithm
        $pairings = $this->generateSwissPairings($standings);

        // Create matches
        $tableNumber = 1;
        foreach ($pairings as $pairing) {
            if ($pairing['isBye']) {
                $byeMatch = $this->createByeMatch($round, $pairing['player1'], $tableNumber);
                $round->addMatch($byeMatch);
            } else {
                $match = $this->createMatch($round, $pairing['player1'], $pairing['player2'], $tableNumber);
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
     * Calculate standings for all players in a tournament.
     *
     * @return array<int, PlayerStandings> Map of registration ID to standings
     */
    public function calculateStandings(Tournament $tournament): array
    {
        $registrations = $tournament->getRegistrations()->toArray();
        $standings = [];

        // Initialize standings for all players
        foreach ($registrations as $registration) {
            $standings[$registration->getId()] = new PlayerStandings($registration);
        }

        // Process all matches from all rounds
        foreach ($tournament->getRounds() as $round) {
            foreach ($round->getMatches() as $match) {
                if (!$match->isCompleted()) {
                    continue;
                }

                $player1 = $match->getPlayer1();
                $player2 = $match->getPlayer2();

                if ($match->isByeMatch()) {
                    // BYE match
                    if (isset($standings[$player1->getId()])) {
                        $standings[$player1->getId()]->addBye();
                    }
                    continue;
                }

                // Track opponents
                if ($player2 !== null) {
                    $standings[$player1->getId()]->addOpponent($player2);
                    $standings[$player2->getId()]->addOpponent($player1);
                }

                // Determine winner/loser
                $winner = $match->getWinner();
                $loser = $match->getLoser();

                if ($winner !== null && $loser !== null) {
                    $standings[$winner->getId()]->addWin();
                    $standings[$loser->getId()]->addLoss();
                } elseif ($winner === null && $loser === null && $player2 !== null) {
                    // Draw (rare in TCG, but supported)
                    $standings[$player1->getId()]->addDraw();
                    $standings[$player2->getId()]->addDraw();
                }
            }
        }

        // Calculate Opponent Match Win Percentage (OMWP) for tiebreakers
        foreach ($standings as $standing) {
            $omwp = $this->calculateOMWP($standing, $standings);
            $standing->setOpponentMatchWinPercentage($omwp);
        }

        return $standings;
    }

    /**
     * Calculate Opponent Match Win Percentage (OMWP) for a player.
     *
     * OMWP is the average match win percentage of all opponents faced.
     * This is the primary tiebreaker in Swiss tournaments.
     *
     * @param PlayerStandings                $standing  The player's standings
     * @param array<int, PlayerStandings> $allStandings All player standings
     */
    private function calculateOMWP(PlayerStandings $standing, array $allStandings): float
    {
        $opponents = $standing->getOpponents();

        if (empty($opponents)) {
            return 0.33; // Minimum per Swiss rules
        }

        $totalMWP = 0.0;
        foreach ($opponents as $opponent) {
            if (isset($allStandings[$opponent->getId()])) {
                $totalMWP += $allStandings[$opponent->getId()]->getMatchWinPercentage();
            }
        }

        return $totalMWP / count($opponents);
    }

    /**
     * Generate Swiss pairings based on current standings.
     *
     * Algorithm:
     * 1. Sort players by match points (descending), then by OMWP (descending)
     * 2. Handle BYE for odd player count
     * 3. Group players by match points
     * 4. Within each group, pair top player with lowest unpaired player they haven't faced
     * 5. If no valid opponent in group, pair down to next group
     *
     * @param array<int, PlayerStandings> $standings
     *
     * @return array<int, array{player1: Registration, player2?: Registration, isBye: bool}>
     *
     * @throws PairingException If valid pairings cannot be generated
     */
    private function generateSwissPairings(array $standings): array
    {
        // Sort by match points (desc), then OMWP (desc)
        $sortedStandings = array_values($standings);
        usort($sortedStandings, function (PlayerStandings $a, PlayerStandings $b): int {
            $pointsDiff = $b->getMatchPoints() <=> $a->getMatchPoints();
            if ($pointsDiff !== 0) {
                return $pointsDiff;
            }

            return $b->getOpponentMatchWinPercentage() <=> $a->getOpponentMatchWinPercentage();
        });

        $pairings = [];
        $pairedPlayers = [];

        // Handle BYE if odd number of players
        if (count($sortedStandings) % 2 !== 0) {
            $byePlayer = $this->selectByePlayerFromStandings($sortedStandings);
            if ($byePlayer !== null) {
                $pairings[] = [
                    'player1' => $byePlayer->getRegistration(),
                    'isBye' => true,
                ];
                $pairedPlayers[$byePlayer->getRegistration()->getId()] = true;
            }
        }

        // Pair remaining players
        foreach ($sortedStandings as $standing) {
            $player = $standing->getRegistration();

            // Skip if already paired
            if (isset($pairedPlayers[$player->getId()])) {
                continue;
            }

            // Find best available opponent
            $opponent = $this->findBestOpponent($standing, $sortedStandings, $pairedPlayers);

            if ($opponent === null) {
                throw PairingException::noValidOpponent($player->getPlayer()->getPseudo());
            }

            $pairings[] = [
                'player1' => $player,
                'player2' => $opponent->getRegistration(),
                'isBye' => false,
            ];

            $pairedPlayers[$player->getId()] = true;
            $pairedPlayers[$opponent->getRegistration()->getId()] = true;
        }

        return $pairings;
    }

    /**
     * Select a player to receive a BYE from standings.
     *
     * Selects the lowest-ranked player who hasn't already received a BYE.
     *
     * @param PlayerStandings[] $sortedStandings Players sorted by rank (highest first)
     */
    private function selectByePlayerFromStandings(array $sortedStandings): ?PlayerStandings
    {
        // Start from bottom of rankings
        for ($i = count($sortedStandings) - 1; $i >= 0; $i--) {
            if (!$sortedStandings[$i]->hasReceivedBye()) {
                return $sortedStandings[$i];
            }
        }

        // If everyone has had a BYE (very rare), give it to the last person
        return $sortedStandings[count($sortedStandings) - 1];
    }

    /**
     * Find the best available opponent for a player.
     *
     * Preference:
     * 1. Same match points, haven't played before
     * 2. Different match points, haven't played before
     * 3. Same match points, rematch (fallback when no other option)
     * 4. Different match points, rematch (fallback when no other option)
     *
     * @param PlayerStandings             $player         The player seeking an opponent
     * @param PlayerStandings[]           $sortedStandings All players sorted by rank
     * @param array<int, bool>            $pairedPlayers  Already paired player IDs
     */
    private function findBestOpponent(
        PlayerStandings $player,
        array $sortedStandings,
        array $pairedPlayers
    ): ?PlayerStandings {
        $playerId = $player->getRegistration()->getId();
        $playerPoints = $player->getMatchPoints();
        $samePointsOpponents = [];
        $otherOpponents = [];
        // Fallback: opponents already faced (rematches)
        $samePointsRematches = [];
        $otherRematches = [];

        foreach ($sortedStandings as $candidate) {
            $candidateId = $candidate->getRegistration()->getId();

            // Skip self
            if ($candidateId === $playerId) {
                continue;
            }

            // Skip already paired
            if (isset($pairedPlayers[$candidateId])) {
                continue;
            }

            $isRematch = $player->hasPlayedAgainst($candidate->getRegistration());

            // Categorize by point group and rematch status
            if ($candidate->getMatchPoints() === $playerPoints) {
                if ($isRematch) {
                    $samePointsRematches[] = $candidate;
                } else {
                    $samePointsOpponents[] = $candidate;
                }
            } else {
                if ($isRematch) {
                    $otherRematches[] = $candidate;
                } else {
                    $otherOpponents[] = $candidate;
                }
            }
        }

        // Priority 1: Prefer opponent with same points, no rematch
        if (!empty($samePointsOpponents)) {
            return $samePointsOpponents[0];
        }

        // Priority 2: Opponent with different points, no rematch
        if (!empty($otherOpponents)) {
            return $otherOpponents[0];
        }

        // Priority 3: Allow rematch with same points (fallback)
        if (!empty($samePointsRematches)) {
            return $samePointsRematches[0];
        }

        // Priority 4: Allow rematch with different points (fallback)
        if (!empty($otherRematches)) {
            return $otherRematches[0];
        }

        return null;
    }

    /**
     * Validate that a tournament can continue (is ongoing and hasn't reached round limit).
     *
     * @throws InvalidTournamentStateException If tournament is not ONGOING
     * @throws PairingException If round limit has been reached
     */
    private function validateTournamentCanContinue(Tournament $tournament): void
    {
        if (!$tournament->isOngoing()) {
            throw new InvalidTournamentStateException(
                $tournament->getStatus(),
                [TournamentStatus::ONGOING],
                'generer la prochaine ronde'
            );
        }

        if ($tournament->hasReachedSwissRoundLimit()) {
            throw new PairingException(sprintf(
                'Limite de rondes Swiss atteinte (%d/%d). Terminez le tournoi.',
                $tournament->getRoundsCount(),
                $tournament->getSwissRounds()
            ));
        }
    }

    /**
     * Validate that a round is complete (all matches finished).
     *
     * @throws RoundNotCompleteException If round has incomplete matches
     */
    private function validateRoundComplete(Round $round): void
    {
        if (!$round->areAllMatchesCompleted()) {
            $totalMatches = $round->getMatches()->count();
            $completedMatches = $round->getCompletedMatchesCount();

            throw new RoundNotCompleteException(
                $round->getRoundNumber(),
                $completedMatches,
                $totalMatches
            );
        }
    }

    /**
     * Validate that a tournament can start.
     *
     * @throws InvalidTournamentStateException If tournament is not in PUBLISHED status
     */
    private function validateTournamentCanStart(Tournament $tournament): void
    {
        if (!$tournament->isPublished()) {
            throw new InvalidTournamentStateException(
                $tournament->getStatus(),
                [TournamentStatus::PUBLISHED],
                'demarrer le tournoi'
            );
        }
    }

    /**
     * Order registrations based on pairing mode.
     *
     * @param Registration[] $registrations
     *
     * @return Registration[]
     */
    private function orderRegistrations(array $registrations, PairingMode $mode): array
    {
        return match ($mode) {
            PairingMode::RANDOM => $this->shuffleRegistrations($registrations),
            PairingMode::REGISTRATION_ORDER => $this->sortByRegistrationOrder($registrations),
        };
    }

    /**
     * Shuffle registrations randomly using cryptographically secure randomization.
     *
     * @param Registration[] $registrations
     *
     * @return Registration[]
     */
    private function shuffleRegistrations(array $registrations): array
    {
        // Use Fisher-Yates shuffle with random_int for cryptographic randomness
        $count = count($registrations);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$registrations[$i], $registrations[$j]] = [$registrations[$j], $registrations[$i]];
        }

        return $registrations;
    }

    /**
     * Sort registrations by registration date (earliest first).
     *
     * @param Registration[] $registrations
     *
     * @return Registration[]
     */
    private function sortByRegistrationOrder(array $registrations): array
    {
        usort(
            $registrations,
            fn (Registration $a, Registration $b): int => $a->getRegisteredAt() <=> $b->getRegisteredAt()
        );

        return $registrations;
    }

    /**
     * Select a player to receive a BYE.
     *
     * For Round 1, the last player in the ordered list receives the BYE.
     * For subsequent rounds, players who haven't had a BYE and have the
     * lowest score are prioritized.
     *
     * @param Registration[] $registrations Ordered list of registrations
     *
     * @return Registration The player who will receive the BYE
     */
    private function selectByePlayer(array $registrations): Registration
    {
        // For Round 1, simply select the last player in the ordered list
        return $registrations[count($registrations) - 1];
    }

    /**
     * Create a match between two players.
     */
    private function createMatch(
        Round $round,
        Registration $player1,
        Registration $player2,
        int $tableNumber
    ): TournamentMatch {
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber($tableNumber);

        return $match;
    }

    /**
     * Create a BYE match for a player.
     *
     * A BYE match is automatically completed with the player as winner
     * and a 2-0 score (standard for TCG BYE matches).
     */
    private function createByeMatch(Round $round, Registration $player, int $tableNumber): TournamentMatch
    {
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player);
        $match->setTableNumber($tableNumber);
        $match->assignBye();

        return $match;
    }
}
