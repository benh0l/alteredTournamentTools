<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByPseudo(string $pseudo): ?User
    {
        return $this->findOneBy(['pseudo' => $pseudo]);
    }

    /**
     * Check if email already exists.
     */
    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    /**
     * Check if pseudo already exists.
     */
    public function pseudoExists(string $pseudo): bool
    {
        return $this->findByPseudo($pseudo) !== null;
    }

    /**
     * Count total users (FR70).
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users who registered this month (FR70).
     */
    public function countThisMonth(): int
    {
        $startOfMonth = new \DateTimeImmutable('first day of this month midnight');
        $endOfMonth = new \DateTimeImmutable('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt <= :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users who have participated in at least one tournament (FR70).
     */
    public function countUniquePlayers(): int
    {
        return (int) $this->getEntityManager()
            ->createQuery(
                'SELECT COUNT(DISTINCT r.player) FROM App\Entity\Registration r'
            )
            ->getSingleScalarResult();
    }

    /**
     * Count users who participated in a tournament this month (FR70).
     */
    public function countActiveThisMonth(): int
    {
        $startOfMonth = new \DateTimeImmutable('first day of this month midnight');
        $endOfMonth = new \DateTimeImmutable('last day of this month 23:59:59');

        return (int) $this->getEntityManager()
            ->createQuery(
                'SELECT COUNT(DISTINCT r.player) FROM App\Entity\Registration r
                 JOIN r.tournament t
                 WHERE t.date >= :start AND t.date <= :end'
            )
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getSingleScalarResult();
    }

    /**
     * Count organizers who have created 2+ tournaments (FR70).
     */
    public function countOrganizersWithMultipleTournaments(): int
    {
        $result = $this->getEntityManager()
            ->createQuery(
                'SELECT COUNT(u.id) FROM App\Entity\User u
                 WHERE (
                     SELECT COUNT(t.id) FROM App\Entity\Tournament t WHERE t.organizer = u
                 ) >= 2'
            )
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Count total organizers (users who created at least 1 tournament) (FR70).
     */
    public function countTotalOrganizers(): int
    {
        return (int) $this->getEntityManager()
            ->createQuery(
                'SELECT COUNT(DISTINCT t.organizer) FROM App\Entity\Tournament t'
            )
            ->getSingleScalarResult();
    }

    /**
     * Get monthly registration counts for the last N months (FR70).
     *
     * @return array<int, array{month: string, count: int}>
     */
    public function getMonthlyRegistrations(int $months = 12): array
    {
        $startDate = (new \DateTimeImmutable())
            ->modify("-{$months} months")
            ->modify('first day of this month midnight');

        $results = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT
                    TO_CHAR(created_at, 'YYYY-MM') as month,
                    COUNT(*) as count
                 FROM \"user\"
                 WHERE created_at >= :start
                 GROUP BY TO_CHAR(created_at, 'YYYY-MM')
                 ORDER BY month ASC",
                ['start' => $startDate->format('Y-m-d H:i:s')]
            )
            ->fetchAllAssociative();

        return array_map(fn ($row) => [
            'month' => $row['month'],
            'count' => (int) $row['count'],
        ], $results);
    }

    /**
     * Search users with filters for admin management (FR74).
     *
     * @return array{users: User[], total: int}
     */
    public function searchUsers(
        ?string $query = null,
        ?string $role = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('u');

        if ($query !== null && $query !== '') {
            $qb->andWhere('LOWER(u.email) LIKE LOWER(:query) OR LOWER(u.pseudo) LIKE LOWER(:query)')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($role !== null && $role !== '') {
            $qb->andWhere('u.roles LIKE :role')
               ->setParameter('role', '%"' . $role . '"%');
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $users = $qb->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'users' => $users,
            'total' => $total,
        ];
    }

    /**
     * Count disputes for a user across all their tournament registrations (FR74).
     */
    public function countDisputesForUser(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQuery(
                "SELECT COUNT(m.id) FROM App\Entity\TournamentMatch m
                 JOIN m.round r
                 WHERE (m.player1 IN (SELECT reg.id FROM App\Entity\Registration reg WHERE reg.player = :user)
                        OR m.player2 IN (SELECT reg2.id FROM App\Entity\Registration reg2 WHERE reg2.player = :user))
                 AND m.disputeHistory IS NOT NULL"
            )
            ->setParameter('user', $user)
            ->getSingleScalarResult();
    }

    /**
     * Find all disputed matches for a user (FR74).
     *
     * @return array<array{match: \App\Entity\TournamentMatch, tournament: \App\Entity\Tournament, opponent: ?User, resolution: ?string}>
     */
    public function findDisputedMatchesForUser(User $user): array
    {
        $matches = $this->getEntityManager()
            ->createQuery(
                "SELECT m, r, t, p1, p2, u1, u2 FROM App\Entity\TournamentMatch m
                 JOIN m.round r
                 JOIN r.tournament t
                 LEFT JOIN m.player1 p1
                 LEFT JOIN m.player2 p2
                 LEFT JOIN p1.player u1
                 LEFT JOIN p2.player u2
                 WHERE (p1.player = :user OR p2.player = :user)
                 AND m.disputeHistory IS NOT NULL
                 ORDER BY r.roundNumber DESC"
            )
            ->setParameter('user', $user)
            ->getResult();

        $result = [];
        foreach ($matches as $match) {
            $history = $match->getDisputeHistory();
            if ($history === null) {
                continue;
            }

            // Check for actual disputes (created with conflicting_submissions)
            $isDispute = false;
            $resolution = null;
            foreach ($history as $entry) {
                if ($entry['type'] === 'created' && ($entry['data']['reason'] ?? '') === 'conflicting_submissions') {
                    $isDispute = true;
                }
                if ($entry['type'] === 'resolved' || $entry['type'] === 'admin_override') {
                    $resolution = $entry['winner'] ?? 'resolved';
                }
            }

            if ($isDispute) {
                $opponent = null;
                if ($match->getPlayer1()?->getPlayer() === $user) {
                    $opponent = $match->getPlayer2()?->getPlayer();
                } else {
                    $opponent = $match->getPlayer1()?->getPlayer();
                }

                $result[] = [
                    'match' => $match,
                    'tournament' => $match->getRound()->getTournament(),
                    'opponent' => $opponent,
                    'resolution' => $resolution,
                ];
            }
        }

        return $result;
    }

    /**
     * Find users by role (FR74).
     *
     * @return User[]
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
