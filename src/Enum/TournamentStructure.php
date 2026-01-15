<?php

declare(strict_types=1);

namespace App\Enum;

enum TournamentStructure: string
{
    case SWISS_ONLY = 'swiss_only';
    case SINGLE_ELIMINATION = 'single_elimination';
    case MIXED = 'mixed';
    case GROUP_STAGE_ELIMINATION = 'group_stage_elimination';

    public function getLabel(): string
    {
        return match ($this) {
            self::SWISS_ONLY => 'enum.tournament_structure.swiss_only',
            self::SINGLE_ELIMINATION => 'enum.tournament_structure.single_elimination',
            self::MIXED => 'enum.tournament_structure.mixed',
            self::GROUP_STAGE_ELIMINATION => 'enum.tournament_structure.group_stage_elimination',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SWISS_ONLY => 'enum.tournament_structure_description.swiss_only',
            self::SINGLE_ELIMINATION => 'enum.tournament_structure_description.single_elimination',
            self::MIXED => 'enum.tournament_structure_description.mixed',
            self::GROUP_STAGE_ELIMINATION => 'enum.tournament_structure_description.group_stage_elimination',
        };
    }

    public function hasGroupStage(): bool
    {
        return $this === self::GROUP_STAGE_ELIMINATION;
    }

    public function hasElimination(): bool
    {
        return in_array($this, [
            self::SINGLE_ELIMINATION,
            self::MIXED,
            self::GROUP_STAGE_ELIMINATION,
        ], true);
    }

    public function hasSwiss(): bool
    {
        return in_array($this, [
            self::SWISS_ONLY,
            self::MIXED,
        ], true);
    }
}
