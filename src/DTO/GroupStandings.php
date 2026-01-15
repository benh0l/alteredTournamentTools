<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Registration;
use App\Entity\TournamentGroup;

/**
 * Data Transfer Object representing a player's standings within a group.
 *
 * Used for calculating group phase standings and determining qualifiers.
 */
final class GroupStandings
{
    private int $wins = 0;
    private int $losses = 0;
    private int $draws = 0;
    private int $gamesWon = 0;
    private int $gamesLost = 0;

    /**
     * Head-to-head results against opponents in this group.
     *
     * @var array<int, bool|null> Map of opponent registration ID => won (true), lost (false), or draw (null)
     */
    private array $headToHead = [];

    public function __construct(
        private readonly Registration $registration,
        private readonly TournamentGroup $group
    ) {
    }

    public function getRegistration(): Registration
    {
        return $this->registration;
    }

    public function getGroup(): TournamentGroup
    {
        return $this->group;
    }

    public function getWins(): int
    {
        return $this->wins;
    }

    public function getLosses(): int
    {
        return $this->losses;
    }

    public function getDraws(): int
    {
        return $this->draws;
    }

    public function getGamesWon(): int
    {
        return $this->gamesWon;
    }

    public function getGamesLost(): int
    {
        return $this->gamesLost;
    }

    public function addWin(int $opponentId, int $playerScore, int $opponentScore): self
    {
        $this->wins++;
        $this->gamesWon += $playerScore;
        $this->gamesLost += $opponentScore;
        $this->headToHead[$opponentId] = true;

        return $this;
    }

    public function addLoss(int $opponentId, int $playerScore, int $opponentScore): self
    {
        $this->losses++;
        $this->gamesWon += $playerScore;
        $this->gamesLost += $opponentScore;
        $this->headToHead[$opponentId] = false;

        return $this;
    }

    public function addDraw(int $opponentId, int $playerScore, int $opponentScore): self
    {
        $this->draws++;
        $this->gamesWon += $playerScore;
        $this->gamesLost += $opponentScore;
        $this->headToHead[$opponentId] = null;

        return $this;
    }

    /**
     * Add a BYE (counts as win with no opponent scores tracked).
     */
    public function addBye(): self
    {
        $this->wins++;
        // BYE counts as 2-0 for game differential
        $this->gamesWon += 2;

        return $this;
    }

    /**
     * Get total match points.
     * Win = 3 points, Draw = 1 point, Loss = 0 points.
     */
    public function getMatchPoints(): int
    {
        return ($this->wins * 3) + $this->draws;
    }

    /**
     * Get total matches played.
     */
    public function getMatchesPlayed(): int
    {
        return $this->wins + $this->losses + $this->draws;
    }

    /**
     * Get point differential (games won - games lost).
     */
    public function getPointDifferential(): int
    {
        return $this->gamesWon - $this->gamesLost;
    }

    /**
     * Get game win percentage.
     */
    public function getGameWinPercentage(): float
    {
        $totalGames = $this->gamesWon + $this->gamesLost;

        if ($totalGames === 0) {
            return 0.0;
        }

        return $this->gamesWon / $totalGames;
    }

    /**
     * Check if this player has won against a specific opponent (head-to-head).
     *
     * @return bool|null True if won, false if lost, null if draw or not played
     */
    public function hasWonAgainst(int $opponentId): ?bool
    {
        return $this->headToHead[$opponentId] ?? null;
    }

    /**
     * Get head-to-head results.
     *
     * @return array<int, bool|null>
     */
    public function getHeadToHead(): array
    {
        return $this->headToHead;
    }

    /**
     * Convert to array for display/serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'registrationId' => $this->registration->getId(),
            'playerName' => $this->registration->getPlayer()->getDisplayName(),
            'groupLetter' => $this->group->getLetter(),
            'wins' => $this->wins,
            'losses' => $this->losses,
            'draws' => $this->draws,
            'matchPoints' => $this->getMatchPoints(),
            'gamesWon' => $this->gamesWon,
            'gamesLost' => $this->gamesLost,
            'pointDifferential' => $this->getPointDifferential(),
            'gameWinPercentage' => $this->getGameWinPercentage(),
        ];
    }
}
