<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\Faction;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;

/**
 * Service for calculating player statistics across all tournaments.
 */
class PlayerStatsService
{
    public function __construct(
        private readonly TournamentMatchRepository $matchRepository,
        private readonly RegistrationRepository $registrationRepository,
    ) {
    }

    /**
     * Get complete player statistics.
     *
     * @return array{
     *     totalMatches: int,
     *     wins: int,
     *     losses: int,
     *     draws: int,
     *     winrate: float,
     *     tournamentsPlayed: int,
     *     bestResult: array{position: int, tournament: string, date: \DateTimeImmutable}|null,
     *     factionStats: array<string, array{played: int, wins: int, winrate: float}>,
     *     frequentOpponents: array<int, array{user: User, matches: int, wins: int, losses: int}>,
     *     recentMatches: array<int, array{match: TournamentMatch, tournament: string, opponent: string|null, won: bool, score: string}>
     * }
     */
    public function getPlayerStats(User $user): array
    {
        $matches = $this->matchRepository->findAllByUser($user);
        $registrations = $this->registrationRepository->findByPlayer($user);

        $stats = [
            'totalMatches' => 0,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'winrate' => 0.0,
            'tournamentsPlayed' => count($registrations),
            'bestResult' => null,
            'factionStats' => [],
            'frequentOpponents' => [],
            'recentMatches' => [],
        ];

        if (empty($matches)) {
            return $stats;
        }

        // Calculate basic stats
        $opponentCounts = [];
        $factionData = [];

        foreach ($matches as $match) {
            // Skip BYE matches for stats
            if ($match->isByeMatch()) {
                continue;
            }

            $stats['totalMatches']++;

            $userRegistration = $this->getUserRegistration($match, $user);
            $opponentRegistration = $match->getOpponent($userRegistration);

            if ($userRegistration === null) {
                continue;
            }

            // Win/Loss/Draw
            $winner = $match->getWinner();
            $won = $winner !== null && $winner->getPlayer()->getId() === $user->getId();
            $lost = $winner !== null && $winner->getPlayer()->getId() !== $user->getId();

            if ($won) {
                $stats['wins']++;
            } elseif ($lost) {
                $stats['losses']++;
            } else {
                $stats['draws']++;
            }

            // Faction stats
            $faction = $userRegistration->getFaction();
            if ($faction !== null) {
                $factionKey = $faction->value;
                if (!isset($factionData[$factionKey])) {
                    $factionData[$factionKey] = ['played' => 0, 'wins' => 0];
                }
                $factionData[$factionKey]['played']++;
                if ($won) {
                    $factionData[$factionKey]['wins']++;
                }
            }

            // Opponent tracking
            if ($opponentRegistration !== null) {
                $opponentUser = $opponentRegistration->getPlayer();
                $opponentId = $opponentUser->getId();

                if (!isset($opponentCounts[$opponentId])) {
                    $opponentCounts[$opponentId] = [
                        'user' => $opponentUser,
                        'matches' => 0,
                        'wins' => 0,
                        'losses' => 0,
                    ];
                }
                $opponentCounts[$opponentId]['matches']++;
                if ($won) {
                    $opponentCounts[$opponentId]['wins']++;
                } elseif ($lost) {
                    $opponentCounts[$opponentId]['losses']++;
                }
            }
        }

        // Calculate winrate
        if ($stats['totalMatches'] > 0) {
            $stats['winrate'] = round(($stats['wins'] / $stats['totalMatches']) * 100, 1);
        }

        // Process faction stats
        foreach ($factionData as $factionKey => $data) {
            $stats['factionStats'][$factionKey] = [
                'played' => $data['played'],
                'wins' => $data['wins'],
                'winrate' => $data['played'] > 0 ? round(($data['wins'] / $data['played']) * 100, 1) : 0.0,
            ];
        }

        // Sort factions by matches played
        uasort($stats['factionStats'], fn($a, $b) => $b['played'] <=> $a['played']);

        // Get top 5 frequent opponents
        usort($opponentCounts, fn($a, $b) => $b['matches'] <=> $a['matches']);
        $stats['frequentOpponents'] = array_slice($opponentCounts, 0, 5);

        // Get recent matches (last 20)
        $recentMatches = array_slice($matches, 0, 20);
        foreach ($recentMatches as $match) {
            $userRegistration = $this->getUserRegistration($match, $user);
            $opponent = $match->getOpponent($userRegistration);
            $winner = $match->getWinner();
            $won = $winner !== null && $winner->getPlayer()->getId() === $user->getId();

            $score = $this->formatScore($match, $userRegistration);

            $stats['recentMatches'][] = [
                'match' => $match,
                'tournament' => $match->getRound()->getTournament()->getName(),
                'tournamentId' => $match->getRound()->getTournament()->getId(),
                'date' => $match->getCompletedAt(),
                'opponent' => $opponent?->getPlayer()->getDisplayName(),
                'opponentId' => $opponent?->getPlayer()->getId(),
                'won' => $won,
                'isBye' => $match->isByeMatch(),
                'score' => $score,
                // User hero info
                'heroImage' => $userRegistration?->getHeroImage(),
                'heroLabel' => $userRegistration?->getHeroLabel(),
                'factionColor' => $userRegistration?->getFaction()?->getColor(),
                // Opponent hero info
                'opponentHeroImage' => $opponent?->getHeroImage(),
                'opponentHeroLabel' => $opponent?->getHeroLabel(),
                'opponentFactionColor' => $opponent?->getFaction()?->getColor(),
            ];
        }

        // Find best result
        $stats['bestResult'] = $this->findBestResult($user, $registrations);

        return $stats;
    }

    /**
     * Get the user's registration for a match.
     */
    private function getUserRegistration(TournamentMatch $match, User $user): ?Registration
    {
        if ($match->getPlayer1()->getPlayer()->getId() === $user->getId()) {
            return $match->getPlayer1();
        }

        if ($match->getPlayer2()?->getPlayer()->getId() === $user->getId()) {
            return $match->getPlayer2();
        }

        return null;
    }

    /**
     * Format match score from user's perspective.
     */
    private function formatScore(TournamentMatch $match, ?Registration $userRegistration): string
    {
        if ($match->isByeMatch()) {
            return 'BYE';
        }

        $result = $match->getResult();
        if ($result === null) {
            return '-';
        }

        $p1Score = $result['player1Score'] ?? 0;
        $p2Score = $result['player2Score'] ?? 0;

        // If user is player1, show as-is; if player2, swap
        if ($userRegistration !== null && $userRegistration->getId() === $match->getPlayer1()->getId()) {
            return sprintf('%d - %d', $p1Score, $p2Score);
        }

        return sprintf('%d - %d', $p2Score, $p1Score);
    }

    /**
     * Find the player's best tournament result.
     *
     * @param Registration[] $registrations
     * @return array{position: int, tournament: string, tournamentId: int, date: \DateTimeImmutable}|null
     */
    private function findBestResult(User $user, array $registrations): ?array
    {
        $bestPosition = null;
        $bestResult = null;

        foreach ($registrations as $registration) {
            $tournament = $registration->getTournament();

            // Only consider completed tournaments
            if (!$tournament->getStatus()->isFinished()) {
                continue;
            }

            // Calculate final position for this registration
            $position = $this->calculateFinalPosition($registration);

            if ($position !== null && ($bestPosition === null || $position < $bestPosition)) {
                $bestPosition = $position;
                $bestResult = [
                    'position' => $position,
                    'tournament' => $tournament->getName(),
                    'tournamentId' => $tournament->getId(),
                    'date' => $tournament->getDate(),
                ];
            }
        }

        return $bestResult;
    }

    /**
     * Calculate a player's final position in a completed tournament.
     */
    private function calculateFinalPosition(Registration $registration): ?int
    {
        $tournament = $registration->getTournament();
        $registrations = $tournament->getRegistrations()->toArray();

        // Build standings based on match points
        $standings = [];
        foreach ($registrations as $reg) {
            $matches = $this->matchRepository->findByPlayer($reg);
            $wins = 0;

            foreach ($matches as $match) {
                if ($match->isCompleted() && $match->getWinner() === $reg) {
                    $wins++;
                }
            }

            $standings[] = [
                'registration' => $reg,
                'wins' => $wins,
            ];
        }

        // Sort by wins descending
        usort($standings, fn($a, $b) => $b['wins'] <=> $a['wins']);

        // Find position
        foreach ($standings as $index => $entry) {
            if ($entry['registration']->getId() === $registration->getId()) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * Get head-to-head statistics between two players.
     *
     * @return array{
     *     opponent: User,
     *     totalMatches: int,
     *     wins: int,
     *     losses: int,
     *     draws: int,
     *     winrate: float,
     *     matches: array
     * }
     */
    public function getHeadToHeadStats(User $user, User $opponent): array
    {
        $allMatches = $this->matchRepository->findAllByUser($user);

        $stats = [
            'opponent' => $opponent,
            'totalMatches' => 0,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'winrate' => 0.0,
            'matches' => [],
        ];

        foreach ($allMatches as $match) {
            if ($match->isByeMatch()) {
                continue;
            }

            $userRegistration = $this->getUserRegistration($match, $user);
            $opponentRegistration = $match->getOpponent($userRegistration);

            if ($opponentRegistration === null) {
                continue;
            }

            // Check if this match is against the specified opponent
            if ($opponentRegistration->getPlayer()->getId() !== $opponent->getId()) {
                continue;
            }

            $stats['totalMatches']++;

            $winner = $match->getWinner();
            $won = $winner !== null && $winner->getPlayer()->getId() === $user->getId();
            $lost = $winner !== null && $winner->getPlayer()->getId() !== $user->getId();

            if ($won) {
                $stats['wins']++;
            } elseif ($lost) {
                $stats['losses']++;
            } else {
                $stats['draws']++;
            }

            $score = $this->formatScore($match, $userRegistration);

            $stats['matches'][] = [
                'match' => $match,
                'tournament' => $match->getRound()->getTournament()->getName(),
                'tournamentId' => $match->getRound()->getTournament()->getId(),
                'date' => $match->getCompletedAt(),
                'won' => $won,
                'score' => $score,
                'roundNumber' => $match->getRound()->getRoundNumber(),
                // User hero info
                'heroImage' => $userRegistration?->getHeroImage(),
                'heroLabel' => $userRegistration?->getHeroLabel(),
                'factionColor' => $userRegistration?->getFaction()?->getColor(),
                // Opponent hero info
                'opponentHeroImage' => $opponentRegistration->getHeroImage(),
                'opponentHeroLabel' => $opponentRegistration->getHeroLabel(),
                'opponentFactionColor' => $opponentRegistration->getFaction()?->getColor(),
            ];
        }

        // Calculate winrate
        if ($stats['totalMatches'] > 0) {
            $stats['winrate'] = round(($stats['wins'] / $stats['totalMatches']) * 100, 1);
        }

        return $stats;
    }
}
