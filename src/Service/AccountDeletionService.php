<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AccountDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResetPasswordRequestRepository $resetPasswordRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Delete a user account and all associated data.
     * RGPD-compliant deletion with transaction safety.
     */
    public function deleteAccount(User $user): void
    {
        $userId = $user->getId();

        $this->entityManager->beginTransaction();

        try {
            // 1. Delete password reset requests
            $this->resetPasswordRepository->removeAllForUser($user);

            // 2. TODO: Delete tournament registrations when Registration entity exists
            // $this->registrationRepository->removeAllForUser($user);

            // 3. TODO: Anonymize match results when Match entity exists
            // $this->anonymizeMatchResults($user);

            // 4. TODO: Handle tournaments created by user when Tournament entity exists
            // $this->handleUserTournaments($user);

            // 5. Delete user entity
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->entityManager->commit();

            // Log for audit (no personal data)
            $this->logger->info('User account deleted', [
                'user_id' => $userId,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->logger->error('Account deletion failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Export all user data in RGPD-compliant format.
     *
     * @return array<string, mixed>
     */
    public function exportUserData(User $user): array
    {
        return [
            'profile' => [
                'email' => $user->getEmail(),
                'pseudo' => $user->getPseudo(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'created_at' => $user->getCreatedAt()->format('c'),
                'updated_at' => $user->getUpdatedAt()?->format('c'),
                'is_verified' => $user->isVerified(),
            ],
            // TODO: Add when entities exist
            // 'registrations' => $this->getRegistrationsData($user),
            // 'tournaments_organized' => $this->getTournamentsData($user),
            // 'match_history' => $this->getMatchHistoryData($user),
            'exported_at' => (new \DateTimeImmutable())->format('c'),
        ];
    }
}
