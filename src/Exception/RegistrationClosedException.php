<?php

declare(strict_types=1);

namespace App\Exception;

final class RegistrationClosedException extends \RuntimeException
{
    public function __construct(string $message = 'Les inscriptions sont fermees.')
    {
        parent::__construct($message);
    }
}
