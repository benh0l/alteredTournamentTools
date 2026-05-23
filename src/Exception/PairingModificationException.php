<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\RoundStatus;
use App\Enum\TournamentStructure;

/**
 * Exception thrown when pairing modification fails.
 */
final class PairingModificationException extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $errorCode,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function roundNotPending(RoundStatus $currentStatus): self
    {
        return new self(
            sprintf(
                'Impossible de modifier les pairings: la ronde est en statut "%s". Seules les rondes en attente (PENDING) peuvent etre modifiees.',
                $currentStatus->value
            ),
            'round_not_pending'
        );
    }

    public static function notSwissFormat(TournamentStructure $structure): self
    {
        return new self(
            sprintf(
                'La modification des pairings n\'est disponible que pour les tournois Swiss. Format actuel: "%s".',
                $structure->value
            ),
            'not_swiss_format'
        );
    }

    public static function playerNotFound(int $playerId): self
    {
        return new self(
            sprintf('Joueur avec l\'ID %d non trouve dans ce tournoi.', $playerId),
            'player_not_found'
        );
    }

    public static function playerDropped(string $playerName): self
    {
        return new self(
            sprintf('Le joueur "%s" a abandonne le tournoi et ne peut pas etre deplace.', $playerName),
            'player_dropped'
        );
    }

    public static function playerNotInRound(): self
    {
        return new self(
            'Un ou plusieurs joueurs ne sont pas dans cette ronde.',
            'player_not_in_round'
        );
    }

    public static function playersInSameMatch(): self
    {
        return new self(
            'Les deux joueurs sont deja dans le meme match. Aucun echange necessaire.',
            'players_in_same_match'
        );
    }

    public static function alreadyHasBye(): self
    {
        return new self(
            'Ce joueur a deja un BYE dans cette ronde.',
            'already_has_bye'
        );
    }
}
