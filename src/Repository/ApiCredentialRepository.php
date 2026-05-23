<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiCredential;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiCredential>
 */
class ApiCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiCredential::class);
    }

    /**
     * Find a credential by its API key.
     */
    public function findByApiKey(string $apiKey): ?ApiCredential
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->where('c.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find an active credential by API key.
     */
    public function findActiveByApiKey(string $apiKey): ?ApiCredential
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->where('c.apiKey = :apiKey')
            ->andWhere('c.isActive = :active')
            ->setParameter('apiKey', $apiKey)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the active credential for a user.
     */
    public function findActiveByUser(User $user): ?ApiCredential
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all credentials for a user (including revoked ones).
     *
     * @return ApiCredential[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total active API credentials.
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get usage statistics for admin dashboard.
     *
     * @return array{total: int, active: int, revoked: int, used_today: int}
     */
    public function getStatistics(): array
    {
        $total = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $today = new \DateTimeImmutable('today');
        $usedToday = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.lastUsedAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'revoked' => $total - $active,
            'used_today' => $usedToday,
        ];
    }
}
