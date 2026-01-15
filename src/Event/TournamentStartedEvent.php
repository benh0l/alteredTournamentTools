<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Tournament;

/**
 * Event dispatched when a tournament is started.
 */
final class TournamentStartedEvent
{
    public function __construct(
        private readonly Tournament $tournament
    ) {
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }
}
