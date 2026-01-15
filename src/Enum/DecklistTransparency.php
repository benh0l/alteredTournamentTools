<?php

declare(strict_types=1);

namespace App\Enum;

enum DecklistTransparency: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'enum.decklist_transparency.open',
            self::CLOSED => 'enum.decklist_transparency.closed',
        };
    }
}
