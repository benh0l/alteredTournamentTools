<?php

declare(strict_types=1);

namespace App\Enum;

enum RoundStatus: string
{
    case PENDING = 'pending';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'enum.round_status.pending',
            self::ONGOING => 'enum.round_status.ongoing',
            self::COMPLETED => 'enum.round_status.completed',
        };
    }

    public function getBadgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-gray-100 text-gray-800',
            self::ONGOING => 'bg-blue-100 text-blue-800',
            self::COMPLETED => 'bg-green-100 text-green-800',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => $newStatus === self::ONGOING,
            self::ONGOING => $newStatus === self::COMPLETED,
            self::COMPLETED => false,
        };
    }
}
