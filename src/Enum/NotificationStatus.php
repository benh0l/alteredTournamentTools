<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Notification delivery status.
 */
enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'enum.notification_status.pending',
            self::SENT => 'enum.notification_status.sent',
            self::FAILED => 'enum.notification_status.failed',
        };
    }

    public function getBadgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-yellow-100 text-yellow-800',
            self::SENT => 'bg-green-100 text-green-800',
            self::FAILED => 'bg-red-100 text-red-800',
        };
    }
}
