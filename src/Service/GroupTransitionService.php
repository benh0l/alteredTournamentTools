<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentGroup;
use App\Entity\TournamentMatch;
use App\Exception\InvalidTournamentStateException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for transitioning from group stage to elimination bracket.
 *
 * Handles:
 * - Seeding qualifiers to prevent same-group rematches
 * - Creating the elimination bracket from qualified players
 */
class GroupTransitionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GroupStageService $groupStageService,
    ) {
    }

    /**
     * Transition from group stage to elimination bracket.
     *
     * @param Tournament $tournament The tournament to transition
     *
     * @return Round The first elimination round
     *
     * @throws InvalidTournamentStateException If group stage not complete
     */
    public function transitionToElimination(Tournament $tournament): Round
    {
        if (!$tournament->hasGroupStage()) {
            throw new InvalidTournamentStateException(
                'Ce tournoi ne supporte pas les phases de poules'
            );
        }

        if (!$this->groupStageService->areGroupMatchesComplete($tournament)) {
            throw new InvalidTournamentStateException(
                'Tous les matchs de poules doivent etre termines avant de passer a l\'elimination'
            );
        }

        // Complete the last group round
        $lastGroupRound = $this->findLastGroupRound($tournament);
        if ($lastGroupRound !== null && !$lastGroupRound->isCompleted()) {
            $lastGroupRound->complete();
        }

        // Get qualifiers and seed them for bracket
        $qualifiers = $this->groupStageService->determineQualifiers($tournament);
        $seededPlayers = $this->seedQualifiersForBracket($tournament, $qualifiers);

        // Determine elimination round number (continues from group rounds)
        $nextRoundNumber = $this->getNextRoundNumber($tournament);

        // Create elimination bracket round
        $round = $this->createEliminationRound($tournament, $seededPlayers, $nextRoundNumber);

        $this->entityManager->flush();

        return $round;
    }

    /**
     * Seed qualifiers to prevent same-group rematches until later rounds.
     *
     * Standard seeding pattern for 4 groups with 2 qualifiers each (8 players):
     * Match 1: A1 vs D2  (Group A 1st vs Group D 2nd)
     * Match 2: B1 vs C2
     * Match 3: C1 vs B2
     * Match 4: D1 vs A2
     *
     * This ensures players from the same group can only meet in the final.
     *
     * @param array<array{registration: Registration, group: TournamentGroup, position: int, points: int, differential: int}> $qualifiers
     *
     * @return Registration[] Seeded players in bracket order
     */
    public function seedQualifiersForBracket(Tournament $tournament, array $qualifiers): array
    {
        $groupCount = $tournament->getGroupCount();
        $qualifiersPerGroup = $tournament->getQualifiersPerGroup();

        if ($groupCount === null || $qualifiersPerGroup === null) {
            throw new InvalidTournamentStateException('Configuration des poules incomplete');
        }

        // Organize qualifiers by group and position
        $byGroupAndPosition = [];
        foreach ($qualifiers as $qualifier) {
            $groupLetter = $qualifier['group']->getLetter();
            $position = $qualifier['position'];
            $byGroupAndPosition[$groupLetter][$position] = $qualifier['registration'];
        }

        // Sort group letters
        $groupLetters = array_keys($byGroupAndPosition);
        sort($groupLetters);

        $totalQualifiers = count($qualifiers);
        $seededPlayers = [];

        if ($qualifiersPerGroup === 2 && $groupCount >= 2) {
            // Standard cross-seeding for 2 qualifiers per group
            // A1 vs last-group-2nd, B1 vs (last-1)-group-2nd, etc.
            $reversedLetters = array_reverse($groupLetters);

            // First place players from each group face second place from opposite side
            for ($i = 0; $i < $groupCount; $i++) {
                $firstLetter = $groupLetters[$i];
                $secondLetter = $reversedLetters[$i];

                // First in bracket order: 1st place player
                $seededPlayers[] = $byGroupAndPosition[$firstLetter][1] ?? null;
                // Second: 2nd place from opposite group
                $seededPlayers[] = $byGroupAndPosition[$secondLetter][2] ?? null;
            }
        } elseif ($qualifiersPerGroup === 1) {
            // Only 1 qualifier per group: standard seeding by group performance
            foreach ($groupLetters as $letter) {
                if (isset($byGroupAndPosition[$letter][1])) {
                    $seededPlayers[] = $byGroupAndPosition[$letter][1];
                }
            }
        } else {
            // More complex seeding for 3+ qualifiers per group
            // Use performance-based seeding within position tiers
            for ($position = 1; $position <= $qualifiersPerGroup; $position++) {
                $tierPlayers = [];
                foreach ($qualifiers as $qualifier) {
                    if ($qualifier['position'] === $position) {
                        $tierPlayers[] = [
                            'registration' => $qualifier['registration'],
                            'points' => $qualifier['points'],
                            'differential' => $qualifier['differential'],
                        ];
                    }
                }

                // Sort tier by points then differential
                usort($tierPlayers, function ($a, $b) {
                    $pointsDiff = $b['points'] <=> $a['points'];
                    if ($pointsDiff !== 0) {
                        return $pointsDiff;
                    }
                    return $b['differential'] <=> $a['differential'];
                });

                foreach ($tierPlayers as $player) {
                    $seededPlayers[] = $player['registration'];
                }
            }
        }

        // Remove any null entries
        $seededPlayers = array_filter($seededPlayers);

        // Ensure we have a power of 2 for clean bracket
        // If not, pad with BYEs (handled in round creation)

        return array_values($seededPlayers);
    }

    /**
     * Create the first elimination round from seeded players.
     *
     * @param Registration[] $seededPlayers
     */
    private function createEliminationRound(Tournament $tournament, array $seededPlayers, int $roundNumber): Round
    {
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber($roundNumber);
        $round->setIsEliminationRound(true);

        $playerCount = count($seededPlayers);

        // Find next power of 2 for bracket size
        $bracketSize = $this->nextPowerOfTwo($playerCount);

        // Pad with nulls for BYEs if needed
        while (count($seededPlayers) < $bracketSize) {
            $seededPlayers[] = null;
        }

        // Generate bracket order for fair seeding
        $bracketOrder = $this->generateBracketOrder($bracketSize);

        $tableNumber = 1;
        $matchCount = $bracketSize / 2;

        for ($i = 0; $i < $matchCount; $i++) {
            $seed1Index = $bracketOrder[$i * 2] - 1;
            $seed2Index = $bracketOrder[$i * 2 + 1] - 1;

            $player1 = $seededPlayers[$seed1Index] ?? null;
            $player2 = $seededPlayers[$seed2Index] ?? null;

            // Skip match if both are BYEs
            if ($player1 === null && $player2 === null) {
                continue;
            }

            $match = new TournamentMatch();
            $match->setRound($round);
            $match->setTableNumber($tableNumber++);

            if ($player1 === null) {
                // Player 2 gets BYE (auto-win)
                $match->setPlayer1($player2);
                $match->assignBye();
            } elseif ($player2 === null) {
                // Player 1 gets BYE (auto-win)
                $match->setPlayer1($player1);
                $match->assignBye();
            } else {
                $match->setPlayer1($player1);
                $match->setPlayer2($player2);
            }

            $round->addMatch($match);
        }

        $round->start();
        $this->entityManager->persist($round);
        $tournament->addRound($round);

        return $round;
    }

    /**
     * Find the last group stage round.
     */
    private function findLastGroupRound(Tournament $tournament): ?Round
    {
        $lastGroupRound = null;

        foreach ($tournament->getRounds() as $round) {
            if (!$round->isEliminationRound()) {
                if ($lastGroupRound === null || $round->getRoundNumber() > $lastGroupRound->getRoundNumber()) {
                    $lastGroupRound = $round;
                }
            }
        }

        return $lastGroupRound;
    }

    /**
     * Get the next round number for the tournament.
     */
    private function getNextRoundNumber(Tournament $tournament): int
    {
        $maxRoundNumber = 0;

        foreach ($tournament->getRounds() as $round) {
            if ($round->getRoundNumber() > $maxRoundNumber) {
                $maxRoundNumber = $round->getRoundNumber();
            }
        }

        return $maxRoundNumber + 1;
    }

    /**
     * Find the next power of 2 >= n.
     */
    private function nextPowerOfTwo(int $n): int
    {
        if ($n <= 0) {
            return 1;
        }

        $power = 1;
        while ($power < $n) {
            $power *= 2;
        }

        return $power;
    }

    /**
     * Generate bracket order for fair seeding.
     *
     * For 8 players: [1, 8, 4, 5, 2, 7, 3, 6]
     * This ensures seed 1 and 2 can only meet in the final.
     *
     * @return int[]
     */
    private function generateBracketOrder(int $size): array
    {
        if ($size === 2) {
            return [1, 2];
        }

        $halfOrder = $this->generateBracketOrder($size / 2);
        $result = [];

        foreach ($halfOrder as $seed) {
            $result[] = $seed;
            $result[] = $size - $seed + 1;
        }

        return $result;
    }
}
