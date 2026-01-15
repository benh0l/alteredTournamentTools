<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * System alert severity levels (FR75).
 */
enum AlertSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::INFO => 'enum.alert_severity.info',
            self::WARNING => 'enum.alert_severity.warning',
            self::CRITICAL => 'enum.alert_severity.critical',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::INFO => 'blue',
            self::WARNING => 'yellow',
            self::CRITICAL => 'red',
        };
    }
}
