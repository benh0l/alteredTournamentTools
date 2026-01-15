<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Repository\TournamentMatchRepository;

/**
 * Service for calculating tournament standings.
 *
 * FR50: Players view live tournament standings.
 * FR53: System displays live standings automatically after each match validation.
 */
class StandingsService
{
    public function __construct(
        private readonly TournamentMatchRepository $matchRepository,
    ) {
    }

    /**
     * Calculate standings for a tournament.
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
        $registrations = $tournament->getRegistrations();
        $standings = [];

        foreach ($registrations as $registration) {
            $playerMatches = $this->matchRepository->findByPlayer($registration);
            $stats = $this->calculatePlayerStats($registration, $playerMatches);
            $standings[] = $stats;
        }

        // Calculate opponent match win percentage for each player
        foreach ($standings as &$entry) {
            $entry['opponentMatchWinPercentage'] = $this->calculateOpponentMatchWinPercentage(
                $entry['registration'],
                $standings
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
     * Calculate stats for a single player.
     *
     * @param TournamentMatch[] $matches
     *
     * @return array{
     *     registration: Registration,
     *     wins: int,
     *     losses: int,
     *     draws: int,
     *     matchPoints: int,
     *     opponentMatchWinPercentage: float,
     *     gameWinPercentage: float
     * }
     */
    private function calculatePlayerStats(Registration $registration, array $matches): array
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
            } elseif ($winner->getId() === $registration->getId()) {
                $wins++;
            } else {
                $losses++;
            }

            // Calculate game stats
            $player1Score = $match->getPlayer1Score();
            $player2Score = $match->getPlayer2Score();

            if ($match->getPlayer1()->getId() === $registration->getId()) {
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
            'registration' => $registration,
            'wins' => $wins,
            'losses' => $losses,
            'draws' => $draws,
            'matchPoints' => $matchPoints,
            'opponentMatchWinPercentage' => 0.0, // Calculated later
            'gameWinPercentage' => $gameWinPercentage,
        ];
    }

    /**
     * Calculate opponent match win percentage.
     *
     * This is the average match win percentage of all opponents the player has faced.
     *
     * @param array<int, array{registration: Registration, matchPoints: int, wins: int, losses: int, draws: int}> $allStandings
     */
    private function calculateOpponentMatchWinPercentage(Registration $registration, array $allStandings): float
    {
        $opponents = $this->matchRepository->findOpponents($registration);

        if (count($opponents) === 0) {
            return 0.0;
        }

        $totalMWP = 0.0;

        foreach ($opponents as $opponent) {
            // Find opponent's stats
            $opponentStats = null;
            foreach ($allStandings as $entry) {
                if ($entry['registration']->getId() === $opponent->getId()) {
                    $opponentStats = $entry;
                    break;
                }
            }

            if ($opponentStats !== null) {
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
        }

        return $totalMWP / count($opponents);
    }
}
