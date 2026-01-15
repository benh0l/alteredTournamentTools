<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when tournament validation fails.
 */
final class TournamentValidationException extends \RuntimeException
{
    /**
     * @param array<string> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Tournament validation failed'
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
