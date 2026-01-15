<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Mode for pairing players in Round 1.
 *
 * RANDOM: Players are randomly shuffled before pairing
 * REGISTRATION_ORDER: Players are paired in the order they registered
 */
enum PairingMode: string
{
    case RANDOM = 'random';
    case REGISTRATION_ORDER = 'registration_order';

    public function getLabel(): string
    {
        return match ($this) {
            self::RANDOM => 'enum.pairing_mode.random',
            self::REGISTRATION_ORDER => 'enum.pairing_mode.registration_order',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::RANDOM => 'enum.pairing_mode_description.random',
            self::REGISTRATION_ORDER => 'enum.pairing_mode_description.registration_order',
        };
    }
}
