<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Valid bracket sizes for single elimination.
 *
 * Bracket sizes are powers of 2 for clean bracket generation.
 * Common sizes in TCG tournaments are 4, 8, 16, 32.
 */
enum BracketSize: int
{
    case TOP_4 = 4;
    case TOP_8 = 8;
    case TOP_16 = 16;
    case TOP_32 = 32;

    public function getLabel(): string
    {
        return match ($this) {
            self::TOP_4 => 'enum.bracket_size.top_4',
            self::TOP_8 => 'enum.bracket_size.top_8',
            self::TOP_16 => 'enum.bracket_size.top_16',
            self::TOP_32 => 'enum.bracket_size.top_32',
        };
    }

    /**
     * Get the number of rounds needed for this bracket size.
     *
     * For single elimination: log2(bracketSize)
     * - Top 4 = 2 rounds (semis + final)
     * - Top 8 = 3 rounds (quarters + semis + final)
     * - Top 16 = 4 rounds
     * - Top 32 = 5 rounds
     */
    public function getRoundsCount(): int
    {
        return (int) log($this->value, 2);
    }

    /**
     * Get the bracket round name.
     *
     * @param int $roundNumber 1-based round number from start of bracket
     */
    public function getRoundName(int $roundNumber): string
    {
        $totalRounds = $this->getRoundsCount();
        $remainingRounds = $totalRounds - $roundNumber + 1;

        return match ($remainingRounds) {
            1 => 'enum.bracket_round.final',
            2 => 'enum.bracket_round.semifinals',
            3 => 'enum.bracket_round.quarterfinals',
            4 => 'enum.bracket_round.round_of_16',
            5 => 'enum.bracket_round.round_of_32',
            default => 'enum.bracket_round.round',
        };
    }

    /**
     * Get all bracket sizes as choices for forms.
     *
     * @return array<string, int>
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $size) {
            $choices[$size->getLabel()] = $size->value;
        }

        return $choices;
    }

    /**
     * Get the minimum number of players needed.
     *
     * For clean brackets, you need at least bracketSize / 2 + 1 players
     * to avoid having entire rounds of byes.
     */
    public function getMinimumPlayers(): int
    {
        return (int) ($this->value / 2) + 1;
    }
}
