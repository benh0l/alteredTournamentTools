<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types of tournament notifications (FR64-FR67).
 */
enum NotificationType: string
{
    case D_MINUS_1 = 'd_minus_1';    // Day before tournament (FR64)
    case H_MINUS_1 = 'h_minus_1';    // Hour before tournament (FR65)
    case ROUND_START = 'round_start'; // Before round start (FR66)

    public function getLabel(): string
    {
        return match ($this) {
            self::D_MINUS_1 => 'enum.notification_type.d_minus_1',
            self::H_MINUS_1 => 'enum.notification_type.h_minus_1',
            self::ROUND_START => 'enum.notification_type.round_start',
        };
    }

    public function getEmailSubjectKey(): string
    {
        return match ($this) {
            self::D_MINUS_1 => 'email.notification.subject.d_minus_1',
            self::H_MINUS_1 => 'email.notification.subject.h_minus_1',
            self::ROUND_START => 'email.notification.subject.round_start',
        };
    }
}
