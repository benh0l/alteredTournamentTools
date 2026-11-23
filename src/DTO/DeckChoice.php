<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\Faction;

/**
 * Deck information declared by one player at registration.
 */
final readonly class DeckChoice
{
    public function __construct(
        public ?string $decklistUrl = null,
        public ?Faction $faction = null,
        public ?Faction $faction2 = null,
        public ?string $hero = null,
    ) {
    }

    /**
     * Build from raw request/form values, where empty strings mean "not provided".
     */
    public static function fromRaw(?string $decklistUrl, ?string $faction, ?string $faction2, ?string $hero): self
    {
        return new self(
            $decklistUrl !== null && $decklistUrl !== '' ? $decklistUrl : null,
            $faction !== null && $faction !== '' ? Faction::tryFrom($faction) : null,
            $faction2 !== null && $faction2 !== '' ? Faction::tryFrom($faction2) : null,
            $hero !== null && $hero !== '' ? $hero : null,
        );
    }
}
