<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for Swiss rounds suggestion.
 *
 * Contains both the base calculation and the adjusted value
 * with explanation for user display.
 */
final readonly class SwissRoundsSuggestion
{
    public function __construct(
        public int $baseRounds,
        public int $suggestedRounds,
        public bool $isBO1Adjusted,
        public string $explanation
    ) {
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'baseRounds' => $this->baseRounds,
            'suggestedRounds' => $this->suggestedRounds,
            'isBO1Adjusted' => $this->isBO1Adjusted,
            'explanation' => $this->explanation,
        ];
    }
}
