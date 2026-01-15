<?php

declare(strict_types=1);

namespace App\Enum;

enum TournamentFormat: string
{
    case CONSTRUCTED_STANDARD = 'constructed_standard';
    case CONSTRUCTED_SINGLETON = 'constructed_singleton';
    case CONSTRUCTED_NUC = 'constructed_nuc';
    case CONSTRUCTED_HERO_OUT_OF_FACTION = 'constructed_hero_oof';
    case CONSTRUCTED_BIFACTION = 'constructed_bifaction';
    case LIMITED = 'limited';

    public function getLabel(): string
    {
        return match ($this) {
            self::CONSTRUCTED_STANDARD => 'enum.tournament_format.constructed_standard',
            self::CONSTRUCTED_SINGLETON => 'enum.tournament_format.constructed_singleton',
            self::CONSTRUCTED_NUC => 'enum.tournament_format.constructed_nuc',
            self::CONSTRUCTED_HERO_OUT_OF_FACTION => 'enum.tournament_format.constructed_hero_oof',
            self::CONSTRUCTED_BIFACTION => 'enum.tournament_format.constructed_bifaction',
            self::LIMITED => 'enum.tournament_format.limited',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CONSTRUCTED_STANDARD => 'enum.tournament_format_description.constructed_standard',
            self::CONSTRUCTED_SINGLETON => 'enum.tournament_format_description.constructed_singleton',
            self::CONSTRUCTED_NUC => 'enum.tournament_format_description.constructed_nuc',
            self::CONSTRUCTED_HERO_OUT_OF_FACTION => 'enum.tournament_format_description.constructed_hero_oof',
            self::CONSTRUCTED_BIFACTION => 'enum.tournament_format_description.constructed_bifaction',
            self::LIMITED => 'enum.tournament_format_description.limited',
        };
    }

    public function isConstructed(): bool
    {
        return match ($this) {
            self::CONSTRUCTED_STANDARD, self::CONSTRUCTED_SINGLETON, self::CONSTRUCTED_NUC, self::CONSTRUCTED_HERO_OUT_OF_FACTION, self::CONSTRUCTED_BIFACTION => true,
            self::LIMITED => false,
        };
    }

    public function isHeroOutOfFaction(): bool
    {
        return $this === self::CONSTRUCTED_HERO_OUT_OF_FACTION;
    }

    public function isBifaction(): bool
    {
        return $this === self::CONSTRUCTED_BIFACTION;
    }
}
