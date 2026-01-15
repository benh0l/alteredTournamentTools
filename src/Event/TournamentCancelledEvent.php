<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Tournament;

/**
 * Event dispatched when a tournament is cancelled.
 */
final class TournamentCancelledEvent
{
    public function __construct(
        private readonly Tournament $tournament,
        private readonly string $reason = ''
    ) {
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
