<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SwissRoundsSuggestion;
use App\Enum\MatchFormat;

/**
 * Calculates recommended Swiss rounds for tournaments.
 *
 * Standard TCG formula: ceil(log2(playerCount))
 *
 * Altered TCG specific rule (FR29):
 * When using BO1 format, add +1 round to reduce variance from single-game results.
 * This ensures more accurate standings despite higher luck factor in BO1.
 *
 * Why +1 for BO1?
 * - Single-game matches have higher variance than best-of-3
 * - More rounds provide more data points for accurate standings
 * - Compensates for potential "lucky wins" in single games
 * - Standard practice in Altered TCG competitive play
 */
final class SwissRoundsCalculator
{
    private const MIN_PLAYERS = 4;
    private const MAX_PLAYERS = 128;
    private const MIN_ROUNDS = 3;
    private const MAX_ROUNDS = 10;

    /**
     * Calculate base Swiss rounds using standard TCG formula.
     *
     * Formula: ceil(log2(playerCount))
     *
     * Examples:
     * - 8 players: log2(8) = 3 rounds
     * - 16 players: log2(16) = 4 rounds
     * - 32 players: log2(32) = 5 rounds
     */
    public function calculateBaseRounds(int $playerCount): int
    {
        if ($playerCount < self::MIN_PLAYERS) {
            return self::MIN_ROUNDS;
        }

        $calculated = (int) ceil(log($playerCount, 2));

        return max(self::MIN_ROUNDS, min(self::MAX_ROUNDS, $calculated));
    }

    /**
     * Calculate Swiss rounds with Altered TCG BO1 adjustment.
     *
     * Altered TCG Rule: BO1 format adds +1 round to standard calculation
     * to compensate for higher variance in single-game matches.
     *
     * Examples with BO1:
     * - 8 players: 3 + 1 = 4 rounds
     * - 16 players: 4 + 1 = 5 rounds
     * - 32 players: 5 + 1 = 6 rounds
     */
    public function calculateWithBO1Rule(int $playerCount, MatchFormat $format): int
    {
        $baseRounds = $this->calculateBaseRounds($playerCount);

        if ($format === MatchFormat::BO1) {
            return min(self::MAX_ROUNDS, $baseRounds + 1);
        }

        return $baseRounds;
    }

    /**
     * Get complete suggestion with explanation for UI display.
     */
    public function getSuggestion(int $playerCount, MatchFormat $format): SwissRoundsSuggestion
    {
        $baseRounds = $this->calculateBaseRounds($playerCount);
        $suggestedRounds = $this->calculateWithBO1Rule($playerCount, $format);
        $isBO1Adjusted = $format === MatchFormat::BO1;

        $explanation = $this->buildExplanation($playerCount, $suggestedRounds, $isBO1Adjusted);

        return new SwissRoundsSuggestion(
            baseRounds: $baseRounds,
            suggestedRounds: $suggestedRounds,
            isBO1Adjusted: $isBO1Adjusted,
            explanation: $explanation
        );
    }

    /**
     * Validate player count is within acceptable range.
     */
    public function isValidPlayerCount(int $playerCount): bool
    {
        return $playerCount >= self::MIN_PLAYERS && $playerCount <= self::MAX_PLAYERS;
    }

    /**
     * Get minimum allowed player count.
     */
    public function getMinPlayers(): int
    {
        return self::MIN_PLAYERS;
    }

    /**
     * Get maximum allowed player count.
     */
    public function getMaxPlayers(): int
    {
        return self::MAX_PLAYERS;
    }

    private function buildExplanation(int $playerCount, int $suggestedRounds, bool $isBO1Adjusted): string
    {
        $explanation = sprintf(
            '%d rondes recommandees pour %d joueurs',
            $suggestedRounds,
            $playerCount
        );

        if ($isBO1Adjusted) {
            $explanation .= ' (Explication: +1 ronde pour BO1 selon les règles d\'Altered TCG)';
        }

        return $explanation;
    }
}
