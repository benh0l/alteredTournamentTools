<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Registration;

/**
 * Event dispatched when a player drops from a tournament.
 */
final class PlayerDroppedEvent
{
    public function __construct(
        private readonly Registration $registration,
        private readonly string $reason = '',
        private readonly bool $byOrganizer = false
    ) {
    }

    public function getRegistration(): Registration
    {
        return $this->registration;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function isByOrganizer(): bool
    {
        return $this->byOrganizer;
    }
}
