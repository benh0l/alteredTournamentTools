<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminMessage>
 *
 * @method AdminMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method AdminMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method AdminMessage[]    findAll()
 * @method AdminMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AdminMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminMessage::class);
    }

    /**
     * Find all messages ordered by sent date (most recent first).
     *
     * @return AdminMessage[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')
            ->addSelect('s')
            ->leftJoin('m.recipientUser', 'r')
            ->addSelect('r')
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with pagination.
     *
     * @return AdminMessage[]
     */
    public function findPaginated(int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')
            ->addSelect('s')
            ->leftJoin('m.recipientUser', 'r')
            ->addSelect('r')
            ->orderBy('m.sentAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total messages.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Save an admin message.
     */
    public function save(AdminMessage $message, bool $flush = false): void
    {
        $this->getEntityManager()->persist($message);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
