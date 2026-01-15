<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when a tournament does not have enough players to start.
 */
final class InsufficientPlayersException extends \RuntimeException
{
    private int $playerCount;
    private int $minimumRequired;

    public function __construct(int $playerCount, int $minimumRequired = 2)
    {
        $this->playerCount = $playerCount;
        $this->minimumRequired = $minimumRequired;

        parent::__construct(sprintf(
            'Le tournoi necessite au moins %d joueurs pour demarrer, %d joueur(s) inscrit(s).',
            $minimumRequired,
            $playerCount
        ));
    }

    public function getPlayerCount(): int
    {
        return $this->playerCount;
    }

    public function getMinimumRequired(): int
    {
        return $this->minimumRequired;
    }
}
