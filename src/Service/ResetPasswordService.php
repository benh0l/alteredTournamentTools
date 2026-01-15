<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class ResetPasswordService
{
    private const TOKEN_LIFETIME_HOURS = 1;
    private const SELECTOR_LENGTH = 20;
    private const VERIFIER_LENGTH = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResetPasswordRequestRepository $resetPasswordRepository,
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
    ) {
    }

    /**
     * Process a password reset request for the given email.
     * Always returns success message to prevent email enumeration.
     */
    public function processResetRequest(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (null === $user) {
            // Don't reveal if email exists - security best practice
            return;
        }

        // Remove any existing reset requests for this user
        $this->resetPasswordRepository->removeAllForUser($user);

        // Create new reset request
        $resetData = $this->createResetRequest($user);

        // Send reset email
        $this->emailService->sendPasswordReset($user, $resetData['fullToken']);
    }

    /**
     * Create a new password reset request for a user.
     *
     * @return array{selector: string, verifier: string, fullToken: string, request: ResetPasswordRequest}
     */
    public function createResetRequest(User $user): array
    {
        $selector = $this->generateToken(self::SELECTOR_LENGTH);
        $verifier = $this->generateToken(self::VERIFIER_LENGTH);
        $hashedToken = $this->hashToken($verifier);

        $expiresAt = new \DateTimeImmutable(sprintf('+%d hours', self::TOKEN_LIFETIME_HOURS));

        $resetRequest = new ResetPasswordRequest(
            $user,
            $selector,
            $hashedToken,
            $expiresAt,
        );

        $this->entityManager->persist($resetRequest);
        $this->entityManager->flush();

        return [
            'selector' => $selector,
            'verifier' => $verifier,
            'fullToken' => $selector . $verifier,
            'request' => $resetRequest,
        ];
    }

    /**
     * Validate a reset token and return the associated request.
     */
    public function validateToken(string $token): ?ResetPasswordRequest
    {
        if (strlen($token) !== self::SELECTOR_LENGTH + self::VERIFIER_LENGTH) {
            return null;
        }

        $selector = substr($token, 0, self::SELECTOR_LENGTH);
        $verifier = substr($token, self::SELECTOR_LENGTH);

        $resetRequest = $this->resetPasswordRepository->findBySelector($selector);

        if (null === $resetRequest) {
            return null;
        }

        if ($resetRequest->isExpired()) {
            $this->removeResetRequest($resetRequest);

            return null;
        }

        // Verify the token using timing-safe comparison
        if (!hash_equals($resetRequest->getHashedToken(), $this->hashToken($verifier))) {
            return null;
        }

        return $resetRequest;
    }

    /**
     * Get the user associated with a valid token.
     */
    public function getUserFromToken(string $token): ?User
    {
        $resetRequest = $this->validateToken($token);

        return $resetRequest?->getUser();
    }

    /**
     * Remove a reset request after it has been used.
     */
    public function removeResetRequest(ResetPasswordRequest $resetRequest): void
    {
        $this->entityManager->remove($resetRequest);
        $this->entityManager->flush();
    }

    /**
     * Remove all reset requests for a user.
     */
    public function removeAllForUser(User $user): void
    {
        $this->resetPasswordRepository->removeAllForUser($user);
    }

    /**
     * Clean up expired reset requests.
     */
    public function removeExpiredRequests(): int
    {
        return $this->resetPasswordRepository->removeExpired();
    }

    /**
     * Generate a cryptographically secure random token.
     */
    private function generateToken(int $length): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash a token for secure storage.
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
