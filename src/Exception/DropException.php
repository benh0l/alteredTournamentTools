<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when a drop operation cannot be performed.
 */
final class DropException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $context Additional context for the exception
     */
    public function __construct(
        string $message,
        private readonly array $context = []
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public static function tournamentNotOngoing(): self
    {
        return new self('Les joueurs ne peuvent abandonner que pendant un tournoi en cours.');
    }

    public static function alreadyDropped(): self
    {
        return new self('Ce joueur a deja abandonne le tournoi.');
    }

    public static function notDropped(): self
    {
        return new self('Ce joueur n\'a pas abandonne le tournoi.');
    }

    public static function activeMatchRequiresForfeit(int $matchId): self
    {
        return new self(
            'Le joueur a un match en cours. Utilisez l\'option de forfait pour continuer.',
            ['matchId' => $matchId]
        );
    }
}
