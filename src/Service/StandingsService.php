<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Repository\TournamentMatchRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Service for calculating tournament standings.
 *
 * FR50: Players view live tournament standings.
 * FR53: System displays live standings automatically after each match validation.
 *
 * Performance optimizations:
 * - Batch loads all matches in a single query (avoids N+1)
 * - Uses Redis cache with 60-second TTL for computed stats
 * - Groups matches by player in memory for O(1) lookup
 */
class StandingsService
{
    public function __construct(
        private readonly TournamentMatchRepository $matchRepository,
        private readonly TagAwareCacheInterface $standingsCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Calculate standings for a tournament.
     * Results are cached for 60 seconds and invalidated on match completion.
     *
     * @return array<int, array{
     *     registration: Registration,
     *     wins: int,
     *     losses: int,
     *     draws: int,
     *     matchPoints: int,
     *     opponentMatchWinPercentage: float,
     *     gameWinPercentage: float
     * }>
     */
    public function calculateStandings(Tournament $tournament): array
    {
        $cacheKey = 'standings_tournament_' . $tournament->getId();

        // Get cached stats (without entities - just IDs and computed values)
        $cachedStats = $this->standingsCache->get($cacheKey, function (ItemInterface $item) use ($tournament) {
            $item->expiresAfter(60); // 1 minute TTL
            $item->tag(['tournament_' . $tournament->getId(), 'standings']);

            $this->logger->debug('Computing standings for tournament {id}', [
                'id' => $tournament->getId(),
            ]);

            return $this->computeStandingsStats($tournament);
        });

        // Reconstruct standings with Registration entities
        return $this->hydrateStandings($tournament, $cachedStats);
    }

    /**
     * Invalidate standings cache for a tournament.
     * Call this when a match is completed or scores change.
     */
    public function invalidateCache(Tournament $tournament): void
    {
        $this->standingsCache->invalidateTags(['tournament_' . $tournament->getId()]);

        $this->logger->debug('Standings cache invalidated for tournament {id}', [
            'id' => $tournament->getId(),
        ]);
    }

    /**
     * Compute standings stats without entities (cache-safe).
     * Returns only IDs and primitive values that can be serialized to Redis.
     *
     * @return array<int, array{
     *     registrationId: int,
     *     wins: int,
     *     losses: int,
     *     draws: int,
     *     matchPoints: int,
     *     opponentMatchWinPercentage: float,
     *     gameWinPercentage: float
     * }>
     */
    private function computeStandingsStats(Tournament $tournament): array
    {
        $registrations = $tournament->getRegistrations();

        // Batch load all matches in ONE query (instead of N queries)
        $allMatches = $this->matchRepository->findAllByTournament($tournament);

        // Group matches by player ID for O(1) lookup
        $matchesByPlayer = [];
        $opponentIdsByPlayer = [];

        foreach ($allMatches as $match) {
            $player1 = $match->getPlayer1();
            $player2 = $match->getPlayer2();

            if ($player1 !== null) {
                $p1Id = $player1->getId();
                $matchesByPlayer[$p1Id][] = $match;

                // Track opponent IDs (excluding BYE matches)
                if ($player2 !== null) {
                    $opponentIdsByPlayer[$p1Id][] = $player2->getId();
                }
            }

            if ($player2 !== null) {
                $p2Id = $player2->getId();
                $matchesByPlayer[$p2Id][] = $match;

                // Track opponent IDs
                if ($player1 !== null) {
                    $opponentIdsByPlayer[$p2Id][] = $player1->getId();
                }
            }
        }

        // Calculate stats for each player
        $standings = [];
        foreach ($registrations as $registration) {
            $playerId = $registration->getId();
            $playerMatches = $matchesByPlayer[$playerId] ?? [];
            $stats = $this->calculatePlayerStats($playerId, $playerMatches);
            $standings[] = $stats;
        }

        // Build standings lookup map for O(1) access
        $standingsMap = [];
        foreach ($standings as $index => $entry) {
            $standingsMap[$entry['registrationId']] = $index;
        }

        // Calculate opponent match win percentage for each player
        foreach ($standings as &$entry) {
            $playerId = $entry['registrationId'];
            $opponentIds = $opponentIdsByPlayer[$playerId] ?? [];

            $entry['opponentMatchWinPercentage'] = $this->calculateOMWP(
                $opponentIds,
                $standings,
                $standingsMap
            );
        }

        // Sort by match points (desc), then by OMW% (desc)
        usort($standings, function ($a, $b) {
            // First sort by match points
            if ($a['matchPoints'] !== $b['matchPoints']) {
                return $b['matchPoints'] <=> $a['matchPoints'];
            }

            // Then by opponent match win percentage
            return $b['opponentMatchWinPercentage'] <=> $a['opponentMatchWinPercentage'];
        });

        return $standings;
    }

    /**
     * Hydrate cached standings with Registration entities.
     *
     * @param array<int, array{registrationId: int, wins: int, losses: int, draws: int, matchPoints: int, opponentMatchWinPercentage: float, gameWinPercentage: float}> $cachedStats
     *
     * @return array<int, array{registration: Registration, wins: int, losses: int, draws: int, matchPoints: int, opponentMatchWinPercentage: float, gameWinPercentage: float}>
     */
    private function hydrateStandings(Tournament $tournament, array $cachedStats): array
    {
        // Build registration lookup map
        $registrationMap = [];
        foreach ($tournament->getRegistrations() as $registration) {
            $registrationMap[$registration->getId()] = $registration;
        }

        // Hydrate standings with entities
        $standings = [];
        foreach ($cachedStats as $stats) {
            $registration = $registrationMap[$stats['registrationId']] ?? null;
            if ($registration === null) {
                continue;
            }

            $standings[] = [
                'registration' => $registration,
                'wins' => $stats['wins'],
                'losses' => $stats['losses'],
                'draws' => $stats['draws'],
                'matchPoints' => $stats['matchPoints'],
                'opponentMatchWinPercentage' => $stats['opponentMatchWinPercentage'],
                'gameWinPercentage' => $stats['gameWinPercentage'],
            ];
        }

        return $standings;
    }

    /**
     * Calculate stats for a single player.
     *
     * @param TournamentMatch[] $matches
     *
     * @return array{
     *     registrationId: int,
     *     wins: int,
     *     losses: int,
     *     draws: int,
     *     matchPoints: int,
     *     opponentMatchWinPercentage: float,
     *     gameWinPercentage: float
     * }
     */
    private function calculatePlayerStats(int $registrationId, array $matches): array
    {
        $wins = 0;
        $losses = 0;
        $draws = 0;
        $gamesWon = 0;
        $gamesPlayed = 0;

        foreach ($matches as $match) {
            if (!$match->isCompleted()) {
                continue;
            }

            // BYE matches count as a win
            if ($match->isByeMatch()) {
                $wins++;
                continue;
            }

            $winner = $match->getWinner();

            if ($winner === null) {
                // Draw (though unlikely in TCG)
                $draws++;
            } elseif ($winner->getId() === $registrationId) {
                $wins++;
            } else {
                $losses++;
            }

            // Calculate game stats
            $player1Score = $match->getPlayer1Score();
            $player2Score = $match->getPlayer2Score();

            if ($match->getPlayer1()->getId() === $registrationId) {
                $gamesWon += $player1Score;
                $gamesPlayed += $player1Score + $player2Score;
            } else {
                $gamesWon += $player2Score;
                $gamesPlayed += $player1Score + $player2Score;
            }
        }

        // Match points: 3 for win, 1 for draw, 0 for loss
        $matchPoints = ($wins * 3) + ($draws * 1);

        // Game win percentage
        $gameWinPercentage = $gamesPlayed > 0
            ? $gamesWon / $gamesPlayed
            : 0.0;

        return [
            'registrationId' => $registrationId,
            'wins' => $wins,
            'losses' => $losses,
            'draws' => $draws,
            'matchPoints' => $matchPoints,
            'opponentMatchWinPercentage' => 0.0, // Calculated later
            'gameWinPercentage' => $gameWinPercentage,
        ];
    }

    /**
     * Calculate opponent match win percentage using pre-built lookup map.
     *
     * @param int[] $opponentIds
     * @param array<int, array{registrationId: int, matchPoints: int, wins: int, losses: int, draws: int}> $standings
     * @param array<int, int> $standingsMap Map of registration ID to standings index
     */
    private function calculateOMWP(array $opponentIds, array $standings, array $standingsMap): float
    {
        if (count($opponentIds) === 0) {
            return 0.0;
        }

        $totalMWP = 0.0;

        foreach ($opponentIds as $opponentId) {
            // O(1) lookup instead of O(n) iteration
            if (!isset($standingsMap[$opponentId])) {
                $totalMWP += 0.33; // Default floor
                continue;
            }

            $opponentStats = $standings[$standingsMap[$opponentId]];
            $opponentMatches = $opponentStats['wins'] + $opponentStats['losses'] + $opponentStats['draws'];

            if ($opponentMatches > 0) {
                // Match win percentage = wins / total matches (minimum 33%)
                $mwp = $opponentStats['wins'] / $opponentMatches;
                $mwp = max($mwp, 0.33); // Floor at 33%
                $totalMWP += $mwp;
            } else {
                $totalMWP += 0.33; // Default floor
            }
        }

        return $totalMWP / count($opponentIds);
    }
}
