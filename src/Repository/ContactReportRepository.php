<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactReport;
use App\Enum\ContactStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactReport>
 */
class ContactReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactReport::class);
    }

    /**
     * Find all reports ordered by creation date (newest first).
     *
     * @return ContactReport[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reports by status.
     *
     * @return ContactReport[]
     */
    public function findByStatus(ContactStatus $status): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count new (unread) reports.
     */
    public function countNew(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', ContactStatus::NEW)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search reports with filters and pagination.
     *
     * @return array{reports: ContactReport[], total: int}
     */
    public function searchReports(
        ?ContactStatus $status = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u');

        if ($status !== null) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $status);
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $reports = $qb->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'reports' => $reports,
            'total' => $total,
        ];
    }
}
