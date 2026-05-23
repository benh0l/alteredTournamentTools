<?php

declare(strict_types=1);

namespace App\Enum;

enum GroupFormationMethod: string
{
    case RANDOM = 'random';

    public function getLabel(): string
    {
        return match ($this) {
            self::RANDOM => 'enum.group_formation_method.random',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::RANDOM => 'enum.group_formation_method_description.random',
        };
    }
}
