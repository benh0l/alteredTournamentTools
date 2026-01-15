<?php

declare(strict_types=1);

namespace App\Enum;

enum GroupFormationMethod: string
{
    case RANDOM = 'random';
    case SERPENTINE = 'serpentine';

    public function getLabel(): string
    {
        return match ($this) {
            self::RANDOM => 'enum.group_formation_method.random',
            self::SERPENTINE => 'enum.group_formation_method.serpentine',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::RANDOM => 'enum.group_formation_method_description.random',
            self::SERPENTINE => 'enum.group_formation_method_description.serpentine',
        };
    }
}
