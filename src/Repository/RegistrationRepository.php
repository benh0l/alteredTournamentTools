<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Registration>
 *
 * @method Registration|null find($id, $lockMode = null, $lockVersion = null)
 * @method Registration|null findOneBy(array $criteria, array $orderBy = null)
 * @method Registration[]    findAll()
 * @method Registration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registration::class);
    }

    /**
     * Find all registrations for a tournament, ordered by registration date.
     *
     * @return Registration[]
     */
    public function findByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('r.registeredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all registrations for a player, ordered by registration date (most recent first).
     *
     * @return Registration[]
     */
    public function findByPlayer(User $player): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.player = :player')
            ->setParameter('player', $player)
            ->orderBy('r.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a specific registration by tournament and player.
     */
    public function findOneByTournamentAndPlayer(Tournament $tournament, User $player): ?Registration
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.tournament = :tournament')
            ->andWhere('r.player = :player')
            ->setParameter('tournament', $tournament)
            ->setParameter('player', $player)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if a player is registered in a tournament.
     */
    public function isPlayerRegistered(Tournament $tournament, User $player): bool
    {
        return $this->findOneByTournamentAndPlayer($tournament, $player) !== null;
    }

    /**
     * Count registrations for a tournament.
     */
    public function countByTournament(Tournament $tournament): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find upcoming tournament registrations for a player.
     *
     * @return Registration[]
     */
    public function findUpcomingByPlayer(User $player): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.tournament', 't')
            ->andWhere('r.player = :player')
            ->andWhere('t.date >= :today')
            ->setParameter('player', $player)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find past tournament registrations for a player.
     *
     * @return Registration[]
     */
    public function findPastByPlayer(User $player): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.tournament', 't')
            ->andWhere('r.player = :player')
            ->andWhere('t.date < :today')
            ->setParameter('player', $player)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a registration by its unregister token.
     */
    public function findByUnregisterToken(string $token): ?Registration
    {
        return $this->findOneBy(['unregisterToken' => $token]);
    }

    /**
     * Find all registrations for a player with tournament data eagerly loaded.
     * Optimized query to avoid N+1 problem.
     *
     * @return Registration[]
     */
    public function findByPlayerWithTournament(User $player): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.tournament', 't')
            ->addSelect('t')
            ->leftJoin('t.organizer', 'o')
            ->addSelect('o')
            ->where('r.player = :player')
            ->setParameter('player', $player)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all registrations for a tournament with player data eagerly loaded.
     * Supports sorting by registration date or player pseudo.
     *
     * @return Registration[]
     */
    public function findByTournamentWithPlayer(
        Tournament $tournament,
        string $sortField = 'registeredAt',
        string $sortDirection = 'ASC'
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.player', 'p')
            ->addSelect('p')
            ->where('r.tournament = :tournament')
            ->setParameter('tournament', $tournament);

        // Handle sorting
        if ($sortField === 'player.pseudo') {
            $qb->orderBy('p.pseudo', $sortDirection);
        } else {
            $qb->orderBy('r.' . $sortField, $sortDirection);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get average number of players per tournament (FR70).
     */
    public function getAveragePlayersPerTournament(): float
    {
        $result = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT AVG(player_count) as avg_count FROM (
                    SELECT COUNT(r.id) as player_count
                    FROM tournaments t
                    LEFT JOIN registrations r ON r.tournament_id = t.id
                    GROUP BY t.id
                    HAVING COUNT(r.id) > 0
                ) counts"
            )
            ->fetchOne();

        return $result !== null ? round((float) $result, 1) : 0.0;
    }

    /**
     * Count total registrations (FR70).
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
