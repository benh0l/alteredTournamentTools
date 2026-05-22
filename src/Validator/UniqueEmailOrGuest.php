<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that an email is unique, but allows registration
 * if the existing account is a guest account (which will be converted).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueEmailOrGuest extends Constraint
{
    public string $message = 'Cette adresse email est deja utilisee';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
