<?php

declare(strict_types=1);

namespace App\Exception;

final class AlreadyRegisteredException extends \RuntimeException
{
    public static function forTournament(int $tournamentId): self
    {
        return new self(sprintf(
            'Vous etes deja inscrit au tournoi #%d.',
            $tournamentId
        ));
    }
}
