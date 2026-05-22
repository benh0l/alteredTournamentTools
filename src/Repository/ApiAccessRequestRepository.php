<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiAccessRequest;
use App\Entity\User;
use App\Enum\ApiRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiAccessRequest>
 */
class ApiAccessRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiAccessRequest::class);
    }

    /**
     * Find all pending requests ordered by creation date (oldest first for FIFO processing).
     *
     * @return ApiAccessRequest[]
     */
    public function findPendingRequests(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->where('r.status = :status')
            ->setParameter('status', ApiRequestStatus::PENDING)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all requests by a specific user.
     *
     * @return ApiAccessRequest[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest request by a user (to check if they already have a pending/approved request).
     */
    public function findLatestByUser(User $user): ?ApiAccessRequest
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if user has a pending or approved request.
     */
    public function hasActiveRequest(User $user): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [ApiRequestStatus::PENDING, ApiRequestStatus::APPROVED])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Count pending requests.
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', ApiRequestStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search requests with filters and pagination.
     *
     * @return array{requests: ApiAccessRequest[], total: int}
     */
    public function searchRequests(
        ?ApiRequestStatus $status = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->leftJoin('r.reviewedBy', 'reviewer')
            ->addSelect('reviewer');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $requests = $qb->orderBy('r.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'requests' => $requests,
            'total' => $total,
        ];
    }
}
