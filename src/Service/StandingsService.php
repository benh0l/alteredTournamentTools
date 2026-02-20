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
 * Standings logic:
 * - Swiss phase: Ranked by match points, then OMW%
 * - Elimination phase: Top cut players ranked by elimination order
 *   (winner = 1st, finalist = 2nd, semi-final losers = 3rd-4th, etc.)
 *   Non-qualified players keep their Swiss ranking after top cut players.
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
     *     gameWinPercentage: float,
     *     eliminationRank: int|null
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
     * Calculate Swiss-only standings (excluding elimination rounds).
     *
     * Useful for displaying qualification standings when a tournament
     * has both Swiss and elimination phases.
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
    public function calculateSwissStandings(Tournament $tournament): array
    {
        $cacheKey = 'standings_swiss_tournament_' . $tournament->getId();

        $cachedStats = $this->standingsCache->get($cacheKey, function (ItemInterface $item) use ($tournament) {
            $item->expiresAfter(60);
            $item->tag(['tournament_' . $tournament->getId(), 'standings']);

            $this->logger->debug('Computing Swiss standings for tournament {id}', [
                'id' => $tournament->getId(),
            ]);

            return $this->computeStandingsStats($tournament, swissOnly: true);
        });

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
     * @param bool $swissOnly If true, only consider Swiss rounds (exclude elimination)
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
    private function computeStandingsStats(Tournament $tournament, bool $swissOnly = false): array
    {
        $registrations = $tournament->getRegistrations();

        // Batch load all matches in ONE query (instead of N queries)
        $allMatches = $this->matchRepository->findAllByTournament($tournament);

        // Filter out elimination matches if swissOnly mode
        if ($swissOnly) {
            $allMatches = array_filter(
                $allMatches,
                fn ($match) => !$match->getRound()->isEliminationRound()
            );
        }

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

        // Sort by match points (desc), then by OMW% (desc) - this is the Swiss ranking
        usort($standings, function ($a, $b) {
            // First sort by match points
            if ($a['matchPoints'] !== $b['matchPoints']) {
                return $b['matchPoints'] <=> $a['matchPoints'];
            }

            // Then by opponent match win percentage
            return $b['opponentMatchWinPercentage'] <=> $a['opponentMatchWinPercentage'];
        });

        // Check if tournament has elimination rounds with completed matches (skip in swissOnly mode)
        if (!$swissOnly) {
            $eliminationRankings = $this->calculateEliminationRankings($tournament, $allMatches);

            if (!empty($eliminationRankings)) {
                // Reorder standings based on elimination results
                $standings = $this->mergeEliminationRankings($standings, $eliminationRankings);
            }
        }

        return $standings;
    }

    /**
     * Calculate elimination rankings based on bracket results.
     *
     * Players are ranked by the round in which they were eliminated:
     * - Winner of final = rank 1
     * - Loser of final = rank 2
     * - Losers of semi-finals = ranks 3-4 (tie-broken by Swiss ranking)
     * - Losers of quarter-finals = ranks 5-8 (tie-broken by Swiss ranking)
     * - etc.
     *
     * @param TournamentMatch[] $allMatches
     *
     * @return array<int, int> Map of registrationId => elimination rank
     */
    private function calculateEliminationRankings(Tournament $tournament, array $allMatches): array
    {
        // Get elimination rounds
        $eliminationRounds = [];
        foreach ($tournament->getRounds() as $round) {
            if ($round->isEliminationRound()) {
                $eliminationRounds[$round->getRoundNumber()] = $round;
            }
        }

        if (empty($eliminationRounds)) {
            return [];
        }

        // Sort rounds by round number (ascending)
        ksort($eliminationRounds);
        $roundNumbers = array_keys($eliminationRounds);

        // Track all players in elimination bracket and eliminated players
        $allTopCutPlayers = []; // All players who participated in elimination
        $eliminatedAt = []; // registrationId => elimination round number
        $activePlayers = []; // Players still in competition (not eliminated)
        $winner = null;

        foreach ($eliminationRounds as $roundNumber => $round) {
            foreach ($round->getMatches() as $match) {
                $player1 = $match->getPlayer1();
                $player2 = $match->getPlayer2();

                // Track all players in elimination bracket
                if ($player1 !== null) {
                    $allTopCutPlayers[$player1->getId()] = true;
                }
                if ($player2 !== null && !$match->isByeMatch()) {
                    $allTopCutPlayers[$player2->getId()] = true;
                }

                if (!$match->isCompleted() || $match->isByeMatch()) {
                    continue;
                }

                $matchWinner = $match->getWinner();

                if ($matchWinner === null || $player1 === null || $player2 === null) {
                    continue;
                }

                // Determine the loser
                $loser = $matchWinner->getId() === $player1->getId() ? $player2 : $player1;
                $eliminatedAt[$loser->getId()] = $roundNumber;

                // Check if this is the final (last round with 1 match)
                $isFinal = $roundNumber === max($roundNumbers) && $round->getMatches()->count() === 1;
                if ($isFinal && $match->isCompleted()) {
                    $winner = $matchWinner->getId();
                }
            }
        }

        // If no top cut players found, return empty
        if (empty($allTopCutPlayers)) {
            return [];
        }

        // Find active players (in top cut but not yet eliminated)
        foreach ($allTopCutPlayers as $playerId => $true) {
            if (!isset($eliminatedAt[$playerId]) && $playerId !== $winner) {
                $activePlayers[$playerId] = true;
            }
        }

        // Calculate ranks based on elimination round
        // The later you're eliminated, the better your rank
        $rankings = [];

        // Winner gets rank 1
        if ($winner !== null) {
            $rankings[$winner] = 1;
        }

        // Active players (still in competition) share rank 1 if no winner yet
        // They will be sorted by Swiss ranking in mergeEliminationRankings
        $currentRank = $winner !== null ? 2 : 1;
        foreach ($activePlayers as $playerId => $true) {
            $rankings[$playerId] = $currentRank;
        }
        if (!empty($activePlayers)) {
            $currentRank += count($activePlayers);
        }

        // Group eliminated players by their elimination round
        $eliminatedByRound = [];
        foreach ($eliminatedAt as $playerId => $roundNum) {
            $eliminatedByRound[$roundNum][] = $playerId;
        }

        // Sort rounds descending (latest first = best rank)
        krsort($eliminatedByRound);

        foreach ($eliminatedByRound as $roundNum => $playerIds) {
            // All players eliminated in the same round share the same base rank
            // (e.g., both semi-final losers are 3rd-4th)
            foreach ($playerIds as $playerId) {
                $rankings[$playerId] = $currentRank;
            }
            // Next rank is after all these players
            $currentRank += count($playerIds);
        }

        return $rankings;
    }

    /**
     * Merge elimination rankings into Swiss standings.
     *
     * Top cut players are ranked by elimination order.
     * Non-qualified players keep their Swiss ranking but are placed after all top cut players.
     *
     * @param array<int, array{registrationId: int, wins: int, losses: int, draws: int, matchPoints: int, opponentMatchWinPercentage: float, gameWinPercentage: float}> $swissStandings
     * @param array<int, int> $eliminationRankings Map of registrationId => elimination rank
     *
     * @return array<int, array{registrationId: int, wins: int, losses: int, draws: int, matchPoints: int, opponentMatchWinPercentage: float, gameWinPercentage: float, eliminationRank: int|null}>
     */
    private function mergeEliminationRankings(array $swissStandings, array $eliminationRankings): array
    {
        // Build Swiss rank lookup (for tie-breaking within same elimination rank)
        $swissRankLookup = [];
        foreach ($swissStandings as $index => $entry) {
            $swissRankLookup[$entry['registrationId']] = $index;
        }

        // Separate top cut players from non-qualified
        $topCutPlayers = [];
        $nonQualifiedPlayers = [];

        foreach ($swissStandings as $entry) {
            $registrationId = $entry['registrationId'];
            if (isset($eliminationRankings[$registrationId])) {
                $entry['eliminationRank'] = $eliminationRankings[$registrationId];
                $topCutPlayers[] = $entry;
            } else {
                $entry['eliminationRank'] = null;
                $nonQualifiedPlayers[] = $entry;
            }
        }

        // Sort top cut players by elimination rank, then by Swiss rank for tie-breaking
        usort($topCutPlayers, function ($a, $b) use ($swissRankLookup) {
            // First by elimination rank (lower = better)
            if ($a['eliminationRank'] !== $b['eliminationRank']) {
                return $a['eliminationRank'] <=> $b['eliminationRank'];
            }

            // Tie-break by Swiss ranking
            $swissRankA = $swissRankLookup[$a['registrationId']] ?? PHP_INT_MAX;
            $swissRankB = $swissRankLookup[$b['registrationId']] ?? PHP_INT_MAX;

            return $swissRankA <=> $swissRankB;
        });

        // Non-qualified players keep their Swiss order (they're already sorted)
        // Merge: top cut first, then non-qualified
        return array_merge($topCutPlayers, $nonQualifiedPlayers);
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
                'eliminationRank' => $stats['eliminationRank'] ?? null,
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
