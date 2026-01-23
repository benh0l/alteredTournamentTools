<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Tournament lifecycle status.
 *
 * State Machine:
 * DRAFT -> PUBLISHED -> ONGOING -> COMPLETED
 *    |        |           |
 *    v        v           v
 * CANCELLED  CANCELLED  CANCELLED
 */
enum TournamentStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'enum.tournament_status.draft',
            self::PUBLISHED => 'enum.tournament_status.published',
            self::ONGOING => 'enum.tournament_status.ongoing',
            self::COMPLETED => 'enum.tournament_status.completed',
            self::CANCELLED => 'enum.tournament_status.cancelled',
        };
    }

    /**
     * Tailwind CSS badge color classes.
     */
    public function getBadgeClasses(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-100 text-gray-800',
            self::PUBLISHED => 'bg-green-100 text-green-800',
            self::ONGOING => 'bg-blue-100 text-blue-800',
            self::COMPLETED => 'bg-purple-100 text-purple-800',
            self::CANCELLED => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Check if status can transition to the given target.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => in_array($target, [self::PUBLISHED, self::CANCELLED], true),
            self::PUBLISHED => in_array($target, [self::ONGOING, self::CANCELLED], true),
            self::ONGOING => in_array($target, [self::COMPLETED, self::CANCELLED], true),
            self::COMPLETED => false,
            self::CANCELLED => false,
        };
    }

    /**
     * Check if tournament configuration is editable.
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if tournament accepts registrations.
     */
    public function acceptsRegistrations(): bool
    {
        return $this === self::PUBLISHED;
    }

    /**
     * Check if tournament is active (published or ongoing).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::PUBLISHED, self::ONGOING], true);
    }

    /**
     * Check if tournament has ended.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * Check if tournament can be deleted.
     * Only DRAFT and PUBLISHED tournaments can be deleted (not started yet).
     */
    public function isDeletable(): bool
    {
        return in_array($this, [self::DRAFT, self::PUBLISHED], true);
    }
}
