<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SystemAlert;
use App\Enum\AlertSeverity;
use App\Enum\AlertType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemAlert>
 */
class SystemAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemAlert::class);
    }

    /**
     * Find all active (unacknowledged) alerts.
     *
     * @return SystemAlert[]
     */
    public function findActiveAlerts(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.acknowledgedAt IS NULL')
            ->orderBy('a.severity', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active alerts by severity.
     *
     * @return array<string, int>
     */
    public function countActiveBySeverity(): array
    {
        $results = $this->createQueryBuilder('a')
            ->select('a.severity, COUNT(a.id) as count')
            ->where('a.acknowledgedAt IS NULL')
            ->groupBy('a.severity')
            ->getQuery()
            ->getResult();

        $counts = [
            AlertSeverity::CRITICAL->value => 0,
            AlertSeverity::WARNING->value => 0,
            AlertSeverity::INFO->value => 0,
        ];

        foreach ($results as $row) {
            $counts[$row['severity']->value] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Check if an active alert of this type already exists.
     */
    public function hasActiveAlertOfType(AlertType $type): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.type = :type')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Find active alert by type.
     */
    public function findActiveByType(AlertType $type): ?SystemAlert
    {
        return $this->createQueryBuilder('a')
            ->where('a.type = :type')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->setParameter('type', $type)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all alerts with filters.
     *
     * @return array{alerts: SystemAlert[], total: int}
     */
    public function findWithFilters(
        ?AlertType $type = null,
        ?AlertSeverity $severity = null,
        ?bool $active = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.acknowledgedBy', 'u')
            ->addSelect('u');

        if ($type !== null) {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }

        if ($severity !== null) {
            $qb->andWhere('a.severity = :severity')->setParameter('severity', $severity);
        }

        if ($active !== null) {
            if ($active) {
                $qb->andWhere('a.acknowledgedAt IS NULL');
            } else {
                $qb->andWhere('a.acknowledgedAt IS NOT NULL');
            }
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $alerts = $qb->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'alerts' => $alerts,
            'total' => $total,
        ];
    }

    /**
     * Delete old acknowledged alerts (cleanup).
     */
    public function deleteOldAcknowledged(int $daysOld = 30): int
    {
        $threshold = new \DateTimeImmutable("-{$daysOld} days");

        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.acknowledgedAt IS NOT NULL')
            ->andWhere('a.acknowledgedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
