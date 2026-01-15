<?php

declare(strict_types=1);

namespace App\Enum;

enum TournamentVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::PUBLIC => 'enum.tournament_visibility.public',
            self::PRIVATE => 'enum.tournament_visibility.private',
        };
    }
}
