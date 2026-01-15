<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when a match result submission is invalid.
 */
class InvalidSubmissionException extends \DomainException
{
    public static function matchAlreadyCompleted(): self
    {
        return new self('Ce match est deja termine.');
    }

    public static function cannotSubmitForBye(): self
    {
        return new self('Impossible de soumettre un resultat pour un BYE.');
    }

    public static function notAParticipant(): self
    {
        return new self('Vous n\'etes pas un participant de ce match.');
    }

    public static function alreadySubmitted(): self
    {
        return new self('Vous avez deja soumis un resultat pour ce match.');
    }

    public static function invalidScore(string $reason = ''): self
    {
        $message = 'Score invalide.';
        if ($reason !== '') {
            $message .= ' ' . $reason;
        }

        return new self($message);
    }

    public static function invalidWinner(): self
    {
        return new self('Le vainqueur declare n\'est pas un participant du match.');
    }
}
