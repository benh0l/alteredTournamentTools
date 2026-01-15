<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * System alert types (FR75).
 */
enum AlertType: string
{
    case ERROR_RATE = 'error_rate';
    case RESPONSE_TIME = 'response_time';
    case PAIRING_FAILURE = 'pairing_failure';
    case MERCURE_DOWN = 'mercure_down';
    case DATABASE_SLOW = 'database_slow';
    case MEMORY_HIGH = 'memory_high';

    public function getLabel(): string
    {
        return match ($this) {
            self::ERROR_RATE => 'enum.alert_type.error_rate',
            self::RESPONSE_TIME => 'enum.alert_type.response_time',
            self::PAIRING_FAILURE => 'enum.alert_type.pairing_failure',
            self::MERCURE_DOWN => 'enum.alert_type.mercure_down',
            self::DATABASE_SLOW => 'enum.alert_type.database_slow',
            self::MEMORY_HIGH => 'enum.alert_type.memory_high',
        };
    }
}
