<?php

namespace App\Enum;

enum ApiRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REVOKED = 'revoked';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'api.status.pending',
            self::APPROVED => 'api.status.approved',
            self::REJECTED => 'api.status.rejected',
            self::REVOKED => 'api.status.revoked',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'bg-yellow-500',
            self::APPROVED => 'bg-green-500',
            self::REJECTED => 'bg-red-500',
            self::REVOKED => 'bg-gray-500',
        };
    }

    public function getBadgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-yellow-100 text-yellow-800',
            self::APPROVED => 'bg-green-100 text-green-800',
            self::REJECTED => 'bg-red-100 text-red-800',
            self::REVOKED => 'bg-gray-100 text-gray-800',
        };
    }
}
