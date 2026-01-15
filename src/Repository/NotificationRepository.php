<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function save(Notification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Notification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Check if a notification of this type was already sent for this user/tournament.
     */
    public function hasBeenSent(User $user, Tournament $tournament, NotificationType $type): bool
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.tournament = :tournament')
            ->andWhere('n.type = :type')
            ->andWhere('n.status = :status')
            ->setParameter('user', $user)
            ->setParameter('tournament', $tournament)
            ->setParameter('type', $type)
            ->setParameter('status', NotificationStatus::SENT)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Find pending notifications for processing.
     *
     * @return Notification[]
     */
    public function findPending(int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->setParameter('status', NotificationStatus::PENDING)
            ->orderBy('n.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notifications for a specific tournament.
     *
     * @return Notification[]
     */
    public function findByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notifications for a specific user.
     *
     * @return Notification[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count notifications by status.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('n')
            ->select('n.status, COUNT(n.id) as count')
            ->groupBy('n.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']->value] = (int) $row['count'];
        }

        return $counts;
    }
}
