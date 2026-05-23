<?php

declare(strict_types=1);

namespace App\Service\Pairing;

use App\Entity\Registration;

/**
 * Represents a player's standings in a Swiss or Round-Robin tournament.
 *
 * Contains:
 * - Match points (3 per win, 0 per loss)
 * - Opponent Match Win Percentage (OMWP) for Swiss tiebreakers
 * - Games won/lost for Round-Robin tiebreakers (BO3 differential)
 * - Head-to-head results for Round-Robin direct confrontation tiebreaker
 * - Whether the player has received a BYE
 *
 * Swiss standard TCG rules:
 * - Win: 3 points
 * - Loss: 0 points
 * - Draw: 1 point (rarely used in TCG)
 * - BYE: Counts as a win (3 points)
 *
 * Round-Robin tiebreaker order:
 * 1. Match points
 * 2. Game differential (games won - games lost)
 * 3. Games won
 * 4. Head-to-head (direct confrontation)
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

    // Round-Robin specific: game-level tracking for BO3 tiebreakers
    private int $gamesWon = 0;
    private int $gamesLost = 0;

    /**
     * Head-to-head results: opponent registration ID => 'win'|'loss'|'draw'
     * @var array<int, string>
     */
    private array $headToHead = [];

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

    // =========================================================================
    // Round-Robin specific methods for game-level tiebreakers
    // =========================================================================

    /**
     * Add game results from a match (for BO3 differential calculation).
     *
     * @param int $won Games won in this match
     * @param int $lost Games lost in this match
     */
    public function addGameResults(int $won, int $lost): self
    {
        $this->gamesWon += $won;
        $this->gamesLost += $lost;

        return $this;
    }

    public function getGamesWon(): int
    {
        return $this->gamesWon;
    }

    public function getGamesLost(): int
    {
        return $this->gamesLost;
    }

    /**
     * Get game differential (games won - games lost).
     * Used as 2nd tiebreaker in Round-Robin.
     */
    public function getGameDifferential(): int
    {
        return $this->gamesWon - $this->gamesLost;
    }

    /**
     * Record head-to-head result against an opponent.
     *
     * @param Registration $opponent The opponent
     * @param string $result 'win', 'loss', or 'draw'
     */
    public function addHeadToHeadResult(Registration $opponent, string $result): self
    {
        $this->headToHead[$opponent->getId()] = $result;

        return $this;
    }

    /**
     * Get head-to-head result against a specific opponent.
     *
     * @return string|null 'win', 'loss', 'draw', or null if not played
     */
    public function getHeadToHeadResult(Registration $opponent): ?string
    {
        return $this->headToHead[$opponent->getId()] ?? null;
    }

    /**
     * Get all head-to-head results.
     *
     * @return array<int, string> Opponent ID => result
     */
    public function getHeadToHeadResults(): array
    {
        return $this->headToHead;
    }

    /**
     * Compare head-to-head with another player for tiebreaker.
     *
     * @param PlayerStandings $other The other player to compare
     * @return int -1 if this player loses H2H, 1 if wins, 0 if draw or not played
     */
    public function compareHeadToHead(PlayerStandings $other): int
    {
        $result = $this->getHeadToHeadResult($other->getRegistration());

        if ($result === null) {
            return 0; // Not played yet
        }

        return match ($result) {
            'win' => 1,
            'loss' => -1,
            'draw' => 0,
            default => 0,
        };
    }
}
