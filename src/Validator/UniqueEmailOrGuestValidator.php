<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validator for UniqueEmailOrGuest constraint.
 *
 * Allows registration if:
 * - The email doesn't exist in the database
 * - The email exists but belongs to a guest account (which will be converted)
 */
final class UniqueEmailOrGuestValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEmailOrGuest) {
            throw new UnexpectedTypeException($constraint, UniqueEmailOrGuest::class);
        }

        if (!$value instanceof User) {
            throw new UnexpectedValueException($value, User::class);
        }

        $email = $value->getEmail();

        if ($email === null || $email === '') {
            return;
        }

        $existingUser = $this->userRepository->findByEmail($email);

        // No existing user - OK
        if ($existingUser === null) {
            return;
        }

        // Same user (editing) - OK
        if ($existingUser->getId() === $value->getId()) {
            return;
        }

        // Existing user is a guest - OK (will be converted)
        if ($existingUser->isGuest()) {
            return;
        }

        // Existing user is NOT a guest - validation error
        $this->context->buildViolation($constraint->message)
            ->atPath('email')
            ->addViolation();
    }
}
