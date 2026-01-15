<?php

declare(strict_types=1);

namespace App\Exception;

final class TournamentNotOpenException extends \RuntimeException
{
    public function __construct(string $message = 'Les inscriptions ne sont pas ouvertes pour ce tournoi.')
    {
        parent::__construct($message);
    }
}
