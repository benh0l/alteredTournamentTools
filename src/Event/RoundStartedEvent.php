<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Round;

/**
 * Event dispatched when a tournament round is started (FR66).
 *
 * This event is dispatched after pairings are generated
 * to trigger notifications to players about their matches.
 */
final class RoundStartedEvent
{
    public function __construct(
        private readonly Round $round
    ) {
    }

    public function getRound(): Round
    {
        return $this->round;
    }
}
