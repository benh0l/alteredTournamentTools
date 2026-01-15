<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\TournamentStatus;

/**
 * Exception thrown when a tournament is in an invalid state for the requested operation.
 */
final class InvalidTournamentStateException extends \RuntimeException
{
    private TournamentStatus $currentStatus;
    /** @var TournamentStatus[] */
    private array $expectedStatuses;

    /**
     * @param TournamentStatus[] $expectedStatuses
     */
    public function __construct(TournamentStatus $currentStatus, array $expectedStatuses, string $operation = '')
    {
        $this->currentStatus = $currentStatus;
        $this->expectedStatuses = $expectedStatuses;

        $expectedLabels = array_map(
            fn (TournamentStatus $status): string => $status->getLabel(),
            $expectedStatuses
        );

        $message = sprintf(
            'Le tournoi est actuellement "%s".',
            $currentStatus->getLabel()
        );

        if (!empty($expectedStatuses)) {
            $message .= sprintf(
                ' Statut(s) attendu(s): %s.',
                implode(' ou ', $expectedLabels)
            );
        }

        if (!empty($operation)) {
            $message = sprintf('Impossible de %s. %s', $operation, $message);
        }

        parent::__construct($message);
    }

    public function getCurrentStatus(): TournamentStatus
    {
        return $this->currentStatus;
    }

    /**
     * @return TournamentStatus[]
     */
    public function getExpectedStatuses(): array
    {
        return $this->expectedStatuses;
    }
}
