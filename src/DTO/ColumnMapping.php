<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for CSV column mapping configuration.
 *
 * Maps CSV column indices to registration fields:
 * - Email (required)
 * - Pseudo, Name, Faction, Faction2, Hero, DecklistUrl (optional)
 */
final class ColumnMapping
{
    public function __construct(
        #[Assert\NotNull(message: 'La colonne Email est requise')]
        #[Assert\PositiveOrZero]
        public readonly ?int $emailIndex,

        public readonly ?int $pseudoIndex = null,
        public readonly ?int $nameIndex = null,
        public readonly ?int $factionIndex = null,
        public readonly ?int $faction2Index = null,
        public readonly ?int $heroIndex = null,
        public readonly ?int $decklistUrlIndex = null,
    ) {
    }

    /**
     * Create a ColumnMapping from form data.
     *
     * @param array<int|string, string|null> $mapping Column index => field name mapping
     */
    public static function fromArray(array $mapping): self
    {
        $indices = [];

        foreach ($mapping as $columnIndex => $fieldName) {
            if ($fieldName && $fieldName !== 'ignore') {
                $indices[$fieldName . 'Index'] = (int) $columnIndex;
            }
        }

        return new self(
            emailIndex: $indices['emailIndex'] ?? null,
            pseudoIndex: $indices['pseudoIndex'] ?? null,
            nameIndex: $indices['nameIndex'] ?? null,
            factionIndex: $indices['factionIndex'] ?? null,
            faction2Index: $indices['faction2Index'] ?? null,
            heroIndex: $indices['heroIndex'] ?? null,
            decklistUrlIndex: $indices['decklistUrlIndex'] ?? null,
        );
    }

    /**
     * Check if the mapping is valid (has email index).
     */
    public function isValid(): bool
    {
        return $this->emailIndex !== null;
    }
}
