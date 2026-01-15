<?php

declare(strict_types=1);

namespace App\Service\Pairing;

use App\Entity\Registration;

/**
 * Represents a player's standings in a Swiss tournament.
 *
 * Contains:
 * - Match points (3 per win, 0 per loss)
 * - Opponent Match Win Percentage (OMWP) for tiebreakers
 * - Whether the player has received a BYE
 *
 * Swiss standard TCG rules:
 * - Win: 3 points
 * - Loss: 0 points
 * - Draw: 1 point (rarely used in TCG)
 * - BYE: Counts as a win (3 points)
 */
final class PlayerStandings
{
    private Registration $registration;
    private int $matchPoints = 0;
    private int $wins = 0;
    private int $losses = 0;
    private int $draws = 0;
    private int $byeCount = 0;
    private float $opponentMatchWinPercentage = 0.0;
    /** @var Registration[] */
    private array $opponents = [];

    public function __construct(Registration $registration)
    {
        $this->registration = $registration;
    }

    public function getRegistration(): Registration
    {
        return $this->registration;
    }

    public function getMatchPoints(): int
    {
        return $this->matchPoints;
    }

    public function setMatchPoints(int $matchPoints): self
    {
        $this->matchPoints = $matchPoints;

        return $this;
    }

    public function getWins(): int
    {
        return $this->wins;
    }

    public function addWin(): self
    {
        $this->wins++;
        $this->matchPoints += 3;

        return $this;
    }

    public function getLosses(): int
    {
        return $this->losses;
    }

    public function addLoss(): self
    {
        $this->losses++;

        return $this;
    }

    public function getDraws(): int
    {
        return $this->draws;
    }

    public function addDraw(): self
    {
        $this->draws++;
        $this->matchPoints += 1;

        return $this;
    }

    public function getByeCount(): int
    {
        return $this->byeCount;
    }

    public function addBye(): self
    {
        $this->byeCount++;
        // BYE counts as a win
        $this->wins++;
        $this->matchPoints += 3;

        return $this;
    }

    public function hasReceivedBye(): bool
    {
        return $this->byeCount > 0;
    }

    public function getOpponentMatchWinPercentage(): float
    {
        return $this->opponentMatchWinPercentage;
    }

    public function setOpponentMatchWinPercentage(float $percentage): self
    {
        $this->opponentMatchWinPercentage = $percentage;

        return $this;
    }

    /**
     * @return Registration[]
     */
    public function getOpponents(): array
    {
        return $this->opponents;
    }

    public function addOpponent(Registration $opponent): self
    {
        $this->opponents[] = $opponent;

        return $this;
    }

    public function hasPlayedAgainst(Registration $opponent): bool
    {
        return in_array($opponent, $this->opponents, true);
    }

    /**
     * Get total matches played (excluding BYEs).
     */
    public function getMatchesPlayed(): int
    {
        return $this->wins + $this->losses + $this->draws - $this->byeCount;
    }

    /**
     * Get match win percentage (minimum 0.33 per Swiss rules).
     *
     * Per Swiss standard TCG rules, the minimum win percentage is 0.33 (33%)
     * to prevent players with very few wins from heavily penalizing their opponents.
     */
    public function getMatchWinPercentage(): float
    {
        $totalMatches = $this->wins + $this->losses + $this->draws;

        if ($totalMatches === 0) {
            return 0.33; // Minimum per Swiss rules
        }

        $percentage = $this->matchPoints / ($totalMatches * 3);

        return max(0.33, $percentage);
    }
}
