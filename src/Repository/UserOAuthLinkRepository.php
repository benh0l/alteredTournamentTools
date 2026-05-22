<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserOAuthLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserOAuthLink>
 */
class UserOAuthLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserOAuthLink::class);
    }

    /**
     * Find a link by provider and provider user ID.
     */
    public function findByProviderUserId(string $provider, string $providerUserId): ?UserOAuthLink
    {
        return $this->findOneBy([
            'provider' => $provider,
            'providerUserId' => $providerUserId,
        ]);
    }

    /**
     * Find a user's link for a specific provider.
     */
    public function findByUserAndProvider(User $user, string $provider): ?UserOAuthLink
    {
        return $this->findOneBy([
            'user' => $user,
            'provider' => $provider,
        ]);
    }

    /**
     * Check if a provider user ID is already linked to any account.
     */
    public function isProviderUserIdLinked(string $provider, string $providerUserId): bool
    {
        return $this->findByProviderUserId($provider, $providerUserId) !== null;
    }

    /**
     * Get the user linked to a provider account.
     */
    public function findUserByProviderUserId(string $provider, string $providerUserId): ?User
    {
        $link = $this->findByProviderUserId($provider, $providerUserId);

        return $link?->getUser();
    }
}
