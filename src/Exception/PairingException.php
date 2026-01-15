<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when the pairing algorithm cannot find valid pairings.
 *
 * This can happen when:
 * - All possible pairings would result in rematches
 * - A player has no valid opponents left
 */
final class PairingException extends \RuntimeException
{
    public function __construct(string $message = 'Impossible de generer les appariements.')
    {
        parent::__construct($message);
    }

    public static function noValidOpponent(string $playerName): self
    {
        return new self(sprintf(
            'Impossible de trouver un adversaire valide pour %s.',
            $playerName
        ));
    }

    public static function allPairsExhausted(): self
    {
        return new self('Tous les appariements possibles ont deja ete joues.');
    }
}
