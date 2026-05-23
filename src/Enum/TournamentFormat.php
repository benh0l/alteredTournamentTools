<?php

declare(strict_types=1);

namespace App\Enum;

enum TournamentFormat: string
{
    case CONSTRUCTED_STANDARD = 'constructed_standard';
    case CONSTRUCTED_NUC = 'constructed_nuc';
    case CONSTRUCTED_SINGLETON = 'constructed_singleton';
    case LIMITED = 'limited';
    case LIMITED_DRAFT = 'limited_draft';
    case CONSTRUCTED_BIFACTION = 'constructed_bifaction';
    case CONSTRUCTED_HERO_OUT_OF_FACTION = 'constructed_hero_oof';
    case FUN_EXPEDITION_KRAKN = 'fun_expedition_krakn';

    public function getLabel(): string
    {
        return match ($this) {
            self::CONSTRUCTED_STANDARD => 'enum.tournament_format.constructed_standard',
            self::CONSTRUCTED_NUC => 'enum.tournament_format.constructed_nuc',
            self::CONSTRUCTED_SINGLETON => 'enum.tournament_format.constructed_singleton',
            self::LIMITED => 'enum.tournament_format.limited',
            self::LIMITED_DRAFT => 'enum.tournament_format.limited_draft',
            self::CONSTRUCTED_BIFACTION => 'enum.tournament_format.constructed_bifaction',
            self::CONSTRUCTED_HERO_OUT_OF_FACTION => 'enum.tournament_format.constructed_hero_oof',
            self::FUN_EXPEDITION_KRAKN => 'enum.tournament_format.fun_expedition_krakn',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CONSTRUCTED_STANDARD => 'enum.tournament_format_description.constructed_standard',
            self::CONSTRUCTED_NUC => 'enum.tournament_format_description.constructed_nuc',
            self::CONSTRUCTED_SINGLETON => 'enum.tournament_format_description.constructed_singleton',
            self::LIMITED => 'enum.tournament_format_description.limited',
            self::LIMITED_DRAFT => 'enum.tournament_format_description.limited_draft',
            self::CONSTRUCTED_BIFACTION => 'enum.tournament_format_description.constructed_bifaction',
            self::CONSTRUCTED_HERO_OUT_OF_FACTION => 'enum.tournament_format_description.constructed_hero_oof',
            self::FUN_EXPEDITION_KRAKN => 'enum.tournament_format_description.fun_expedition_krakn',
        };
    }

    public function isConstructed(): bool
    {
        return match ($this) {
            self::CONSTRUCTED_STANDARD, self::CONSTRUCTED_SINGLETON, self::CONSTRUCTED_NUC, self::CONSTRUCTED_HERO_OUT_OF_FACTION, self::CONSTRUCTED_BIFACTION, self::FUN_EXPEDITION_KRAKN => true,
            self::LIMITED, self::LIMITED_DRAFT => false,
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

    public function isExpeditionKrakn(): bool
    {
        return $this === self::FUN_EXPEDITION_KRAKN;
    }

    public function hasRestrictedHeroes(): bool
    {
        return $this === self::FUN_EXPEDITION_KRAKN;
    }
}
