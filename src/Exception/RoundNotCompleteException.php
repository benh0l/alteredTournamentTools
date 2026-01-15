<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when attempting to start a new round before the current round is complete.
 */
final class RoundNotCompleteException extends \RuntimeException
{
    private int $roundNumber;
    private int $completedMatches;
    private int $totalMatches;

    public function __construct(int $roundNumber, int $completedMatches, int $totalMatches)
    {
        $this->roundNumber = $roundNumber;
        $this->completedMatches = $completedMatches;
        $this->totalMatches = $totalMatches;

        $remaining = $totalMatches - $completedMatches;
        parent::__construct(sprintf(
            'La ronde %d n\'est pas terminee. %d match(s) restant(s) sur %d.',
            $roundNumber,
            $remaining,
            $totalMatches
        ));
    }

    public function getRoundNumber(): int
    {
        return $this->roundNumber;
    }

    public function getCompletedMatches(): int
    {
        return $this->completedMatches;
    }

    public function getTotalMatches(): int
    {
        return $this->totalMatches;
    }

    public function getRemainingMatches(): int
    {
        return $this->totalMatches - $this->completedMatches;
    }
}
