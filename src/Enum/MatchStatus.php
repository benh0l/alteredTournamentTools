<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchStatus: string
{
    case PENDING = 'pending';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case DISPUTE = 'dispute';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'enum.match_status.pending',
            self::ONGOING => 'enum.match_status.ongoing',
            self::COMPLETED => 'enum.match_status.completed',
            self::DISPUTE => 'enum.match_status.dispute',
        };
    }

    public function getBadgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-gray-100 text-gray-800',
            self::ONGOING => 'bg-blue-100 text-blue-800',
            self::COMPLETED => 'bg-green-100 text-green-800',
            self::DISPUTE => 'bg-red-100 text-red-800',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::COMPLETED;
    }

    public function requiresResolution(): bool
    {
        return $this === self::DISPUTE;
    }
}
