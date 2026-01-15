<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchFormat: string
{
    case BO1 = 'bo1';
    case BO3 = 'bo3';

    public function getLabel(): string
    {
        return match ($this) {
            self::BO1 => 'enum.match_format.bo1',
            self::BO3 => 'enum.match_format.bo3',
        };
    }
}
