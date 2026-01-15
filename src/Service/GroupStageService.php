<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\GroupStandings;
use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentGroup;
use App\Entity\TournamentMatch;
use App\Enum\GroupFormationMethod;
use App\Enum\MatchStatus;
use App\Enum\RoundStatus;
use App\Enum\TournamentStatus;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Repository\TournamentGroupRepository;
use App\Repository\TournamentMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing group stage operations in GROUP_STAGE_ELIMINATION tournaments.
 *
 * Responsibilities:
 * - Form groups from tournament registrations
 * - Generate round-robin pairings within groups
 * - Calculate standings per group
 * - Determine qualifiers from each group
 */
class GroupStageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TournamentMatchRepository $matchRepository,
        private TournamentGroupRepository $groupRepository,
    ) {
    }

    /**
     * Form groups from tournament registrations.
     *
     * @param Tournament            $tournament The tournament to form groups for
     * @param GroupFormationMethod  $method     How to assign players to groups
     *
     * @return TournamentGroup[] The created groups
     *
     * @throws InvalidTournamentStateException If tournament is not ready for group formation
     * @throws InsufficientPlayersException    If not enough players registered
     */
    public function formGroups(Tournament $tournament, ?GroupFormationMethod $method = null): array
    {
        // Validate tournament state
        if ($tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            throw new InvalidTournamentStateException(
                'Le tournoi doit etre en statut PUBLISHED pour former les poules'
            );
        }

        if (!$tournament->hasGroupStage()) {
            throw new InvalidTournamentStateException(
                'Ce tournoi ne supporte pas les phases de poules'
            );
        }

        if ($tournament->hasGroups()) {
            throw new InvalidTournamentStateException(
                'Les poules ont deja ete formees pour ce tournoi'
            );
        }

        $groupCount = $tournament->getGroupCount();
        $playersPerGroup = $tournament->getPlayersPerGroup();

        if ($groupCount === null || $playersPerGroup === null) {
            throw new InvalidTournamentStateException(
                'La configuration des poules est incomplete'
            );
        }

        $registrations = $tournament->getRegistrations()->toArray();
        $playerCount = count($registrations);
        $expectedPlayers = $groupCount * $playersPerGroup;

        if ($playerCount < $expectedPlayers) {
            throw new InsufficientPlayersException(
                sprintf('Pas assez de joueurs: %d inscrits, %d attendus', $playerCount, $expectedPlayers)
            );
        }

        $method = $method ?? $tournament->getGroupFormationMethod() ?? GroupFormationMethod::RANDOM;

        // Order players according to formation method
        $orderedPlayers = $this->orderPlayersForGroups($registrations, $method);

        // Distribute players to groups
        $groups = $this->distributePlayersToGroups($tournament, $orderedPlayers, $groupCount);

        // Persist groups
        foreach ($groups as $group) {
            $this->entityManager->persist($group);
            $tournament->addGroup($group);
        }

        // Update tournament status to ONGOING
        $tournament->setStatus(TournamentStatus::ONGOING);
        $tournament->setStartedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $groups;
    }

    /**
     * Generate a round of group stage matches for all groups.
     *
     * Uses the circle method (round-robin algorithm) to generate fair pairings.
     *
     * @param Tournament $tournament   The tournament
     * @param int        $roundNumber  The round number within group stage (1, 2, 3...)
     *
     * @return Round The created round with matches from all groups
     */
    public function generateGroupRound(Tournament $tournament, int $roundNumber): Round
    {
        if (!$tournament->isOngoing()) {
            throw new InvalidTournamentStateException(
                'Le tournoi doit etre en cours pour generer une ronde'
            );
        }

        if (!$tournament->hasGroups()) {
            throw new InvalidTournamentStateException(
                'Les poules doivent etre formees avant de generer des rondes'
            );
        }

        // Check if previous round is complete (if not round 1)
        if ($roundNumber > 1) {
            $previousRound = $this->findGroupRound($tournament, $roundNumber - 1);
            if ($previousRound === null || !$previousRound->areAllMatchesCompleted()) {
                throw new InvalidTournamentStateException(
                    'La ronde precedente doit etre terminee'
                );
            }
            $previousRound->complete();
        }

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber($roundNumber);
        $round->setIsEliminationRound(false);

        $tableNumber = 1;

        foreach ($tournament->getGroups() as $group) {
            $players = $group->getPlayers()->toArray();
            $pairings = $this->generateRoundRobinPairings($players, $roundNumber);

            foreach ($pairings as $pairing) {
                $match = new TournamentMatch();
                $match->setRound($round);
                $match->setGroup($group);
                $match->setTableNumber($tableNumber++);
                $match->setPlayer1($pairing['player1']);

                if (isset($pairing['player2'])) {
                    $match->setPlayer2($pairing['player2']);
                } else {
                    // BYE match
                    $match->assignBye();
                }

                $round->addMatch($match);
            }
        }

        $round->start();
        $this->entityManager->persist($round);
        $tournament->addRound($round);
        $this->entityManager->flush();

        return $round;
    }

    /**
     * Calculate standings for a specific group.
     *
     * @param TournamentGroup $group The group to calculate standings for
     *
     * @return GroupStandings[] Standings sorted by: points DESC, head-to-head, differential DESC
     */
    public function calculateGroupStandings(TournamentGroup $group): array
    {
        $standings = [];

        // Initialize standings for each player
        foreach ($group->getPlayers() as $registration) {
            $standings[$registration->getId()] = new GroupStandings($registration, $group);
        }

        // Get all completed matches for this group
        $matches = $this->matchRepository->findCompletedByGroup($group);

        foreach ($matches as $match) {
            $player1 = $match->getPlayer1();
            $player2 = $match->getPlayer2();

            // Handle BYE matches
            if ($match->isByeMatch()) {
                if (isset($standings[$player1->getId()])) {
                    $standings[$player1->getId()]->addBye();
                }
                continue;
            }

            $winner = $match->getWinner();
            $player1Score = $match->getPlayer1Score();
            $player2Score = $match->getPlayer2Score();

            if ($winner === null) {
                // Draw
                if (isset($standings[$player1->getId()])) {
                    $standings[$player1->getId()]->addDraw($player2->getId(), $player1Score, $player2Score);
                }
                if (isset($standings[$player2->getId()])) {
                    $standings[$player2->getId()]->addDraw($player1->getId(), $player2Score, $player1Score);
                }
            } elseif ($winner->getId() === $player1->getId()) {
                // Player 1 won
                if (isset($standings[$player1->getId()])) {
                    $standings[$player1->getId()]->addWin($player2->getId(), $player1Score, $player2Score);
                }
                if (isset($standings[$player2->getId()])) {
                    $standings[$player2->getId()]->addLoss($player1->getId(), $player2Score, $player1Score);
                }
            } else {
                // Player 2 won
                if (isset($standings[$player1->getId()])) {
                    $standings[$player1->getId()]->addLoss($player2->getId(), $player1Score, $player2Score);
                }
                if (isset($standings[$player2->getId()])) {
                    $standings[$player2->getId()]->addWin($player1->getId(), $player2Score, $player1Score);
                }
            }
        }

        // Sort standings
        $sortedStandings = array_values($standings);
        usort($sortedStandings, function (GroupStandings $a, GroupStandings $b): int {
            // 1. Match points (descending)
            $pointsDiff = $b->getMatchPoints() <=> $a->getMatchPoints();
            if ($pointsDiff !== 0) {
                return $pointsDiff;
            }

            // 2. Head-to-head (only if exactly 2 players tied)
            $h2h = $a->hasWonAgainst($b->getRegistration()->getId());
            if ($h2h === true) {
                return -1; // A won against B
            }
            if ($h2h === false) {
                return 1;  // A lost to B
            }

            // 3. Point differential (descending)
            $diffDiff = $b->getPointDifferential() <=> $a->getPointDifferential();
            if ($diffDiff !== 0) {
                return $diffDiff;
            }

            // 4. Total games won (descending)
            return $b->getGamesWon() <=> $a->getGamesWon();
        });

        return $sortedStandings;
    }

    /**
     * Determine qualified players from all groups.
     *
     * @param Tournament $tournament The tournament
     *
     * @return array<array{registration: Registration, group: TournamentGroup, position: int, points: int, differential: int}>
     */
    public function determineQualifiers(Tournament $tournament): array
    {
        $qualifiersPerGroup = $tournament->getQualifiersPerGroup();

        if ($qualifiersPerGroup === null) {
            throw new InvalidTournamentStateException('Nombre de qualifies par poule non configure');
        }

        $qualifiers = [];

        foreach ($tournament->getGroups() as $group) {
            $standings = $this->calculateGroupStandings($group);

            for ($i = 0; $i < $qualifiersPerGroup && $i < count($standings); $i++) {
                $standing = $standings[$i];
                $qualifiers[] = [
                    'registration' => $standing->getRegistration(),
                    'group' => $group,
                    'position' => $i + 1,
                    'points' => $standing->getMatchPoints(),
                    'differential' => $standing->getPointDifferential(),
                ];
            }
        }

        return $qualifiers;
    }

    /**
     * Check if all group stage matches are complete.
     */
    public function areGroupMatchesComplete(Tournament $tournament): bool
    {
        if (!$tournament->hasGroups()) {
            return false;
        }

        // Find the maximum required rounds (based on largest group)
        $maxRequiredRounds = 0;
        foreach ($tournament->getGroups() as $group) {
            $requiredRounds = $group->getRequiredRounds();
            if ($requiredRounds > $maxRequiredRounds) {
                $maxRequiredRounds = $requiredRounds;
            }
        }

        // Check all group rounds are complete
        for ($roundNum = 1; $roundNum <= $maxRequiredRounds; $roundNum++) {
            $round = $this->findGroupRound($tournament, $roundNum);
            if ($round === null || !$round->areAllMatchesCompleted()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the number of rounds needed for round-robin in a group.
     */
    public function getRequiredRoundsForGroup(int $playerCount): int
    {
        if ($playerCount <= 1) {
            return 0;
        }

        // For odd number: N rounds (each player gets a bye)
        // For even number: N-1 rounds
        return $playerCount % 2 === 0 ? $playerCount - 1 : $playerCount;
    }

    /**
     * Find a specific group round by number.
     */
    public function findGroupRound(Tournament $tournament, int $roundNumber): ?Round
    {
        foreach ($tournament->getRounds() as $round) {
            if ($round->getRoundNumber() === $roundNumber && !$round->isEliminationRound()) {
                return $round;
            }
        }

        return null;
    }

    /**
     * Order players according to formation method.
     *
     * @param Registration[] $registrations
     * @return Registration[]
     */
    private function orderPlayersForGroups(array $registrations, GroupFormationMethod $method): array
    {
        if ($method === GroupFormationMethod::RANDOM) {
            // Fisher-Yates shuffle
            $count = count($registrations);
            for ($i = $count - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$registrations[$i], $registrations[$j]] = [$registrations[$j], $registrations[$i]];
            }
            return $registrations;
        }

        // SERPENTINE: assume registrations are already in seed order
        // Just return as-is; distribution will handle serpentine pattern
        return $registrations;
    }

    /**
     * Distribute players to groups using serpentine pattern.
     *
     * For 4 groups with seeds 1-16:
     * Group A: 1, 8, 9, 16
     * Group B: 2, 7, 10, 15
     * Group C: 3, 6, 11, 14
     * Group D: 4, 5, 12, 13
     *
     * @param Registration[] $players
     * @return TournamentGroup[]
     */
    private function distributePlayersToGroups(Tournament $tournament, array $players, int $groupCount): array
    {
        $groups = [];

        // Create groups
        for ($i = 0; $i < $groupCount; $i++) {
            $letter = chr(ord('A') + $i);
            $group = new TournamentGroup();
            $group->setTournament($tournament);
            $group->setLetter($letter);
            $group->setName('Poule ' . $letter);
            $groups[$i] = $group;
        }

        $method = $tournament->getGroupFormationMethod() ?? GroupFormationMethod::RANDOM;

        if ($method === GroupFormationMethod::SERPENTINE) {
            // Serpentine distribution
            $direction = 1;
            $groupIndex = 0;

            foreach ($players as $player) {
                $groups[$groupIndex]->addPlayer($player);

                $groupIndex += $direction;

                // Reverse at boundaries
                if ($groupIndex >= $groupCount) {
                    $groupIndex = $groupCount - 1;
                    $direction = -1;
                } elseif ($groupIndex < 0) {
                    $groupIndex = 0;
                    $direction = 1;
                }
            }
        } else {
            // Random: simple round-robin distribution
            foreach ($players as $index => $player) {
                $groupIndex = $index % $groupCount;
                $groups[$groupIndex]->addPlayer($player);
            }
        }

        return $groups;
    }

    /**
     * Generate round-robin pairings using the circle method.
     *
     * @param Registration[] $players
     * @param int           $roundNumber
     *
     * @return array<array{player1: Registration, player2?: Registration}>
     */
    private function generateRoundRobinPairings(array $players, int $roundNumber): array
    {
        $n = count($players);

        if ($n <= 1) {
            return [];
        }

        // Add phantom player for odd count (BYE)
        $hasPhantom = false;
        if ($n % 2 !== 0) {
            $players[] = null;
            $n++;
            $hasPhantom = true;
        }

        // Rotate for round (keep first player fixed)
        $rotated = $this->rotateForRound($players, $roundNumber);

        // Create pairings
        $pairings = [];
        $half = $n / 2;

        for ($i = 0; $i < $half; $i++) {
            $player1 = $rotated[$i];
            $player2 = $rotated[$n - 1 - $i];

            // Skip if both are phantom (shouldn't happen)
            if ($player1 === null && $player2 === null) {
                continue;
            }

            // Handle BYE
            if ($player1 === null) {
                $pairings[] = ['player1' => $player2]; // BYE
            } elseif ($player2 === null) {
                $pairings[] = ['player1' => $player1]; // BYE
            } else {
                $pairings[] = ['player1' => $player1, 'player2' => $player2];
            }
        }

        return $pairings;
    }

    /**
     * Rotate players for a specific round using circle method.
     * First player stays fixed, others rotate.
     *
     * @param array<Registration|null> $players
     * @return array<Registration|null>
     */
    private function rotateForRound(array $players, int $roundNumber): array
    {
        $n = count($players);

        if ($n <= 2) {
            return $players;
        }

        // Number of rotations needed (0-indexed round)
        $rotations = $roundNumber - 1;

        // Keep first player fixed
        $first = $players[0];
        $rest = array_slice($players, 1);

        // Rotate the rest
        $rotations = $rotations % count($rest);
        $rotated = array_merge(
            array_slice($rest, $rotations),
            array_slice($rest, 0, $rotations)
        );

        return array_merge([$first], $rotated);
    }
}
