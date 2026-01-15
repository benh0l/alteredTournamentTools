<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Round;
use App\Entity\Tournament;
use App\Enum\RoundStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Round>
 *
 * @method Round|null find($id, $lockMode = null, $lockVersion = null)
 * @method Round|null findOneBy(array $criteria, array $orderBy = null)
 * @method Round[]    findAll()
 * @method Round[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Round::class);
    }

    /**
     * Find all rounds for a tournament, ordered by round number.
     *
     * @return Round[]
     */
    public function findByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('r.roundNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the current (ongoing) round for a tournament.
     */
    public function findCurrentRound(Tournament $tournament): ?Round
    {
        return $this->createQueryBuilder('r')
            ->where('r.tournament = :tournament')
            ->andWhere('r.status = :status')
            ->setParameter('tournament', $tournament)
            ->setParameter('status', RoundStatus::ONGOING)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the latest round for a tournament (highest round number).
     */
    public function findLatestRound(Tournament $tournament): ?Round
    {
        return $this->createQueryBuilder('r')
            ->where('r.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('r.roundNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find completed rounds for a tournament.
     *
     * @return Round[]
     */
    public function findCompletedRounds(Tournament $tournament): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.tournament = :tournament')
            ->andWhere('r.status = :status')
            ->setParameter('tournament', $tournament)
            ->setParameter('status', RoundStatus::COMPLETED)
            ->orderBy('r.roundNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a specific round by tournament and round number.
     */
    public function findByTournamentAndNumber(Tournament $tournament, int $roundNumber): ?Round
    {
        return $this->createQueryBuilder('r')
            ->where('r.tournament = :tournament')
            ->andWhere('r.roundNumber = :roundNumber')
            ->setParameter('tournament', $tournament)
            ->setParameter('roundNumber', $roundNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count rounds for a tournament.
     */
    public function countByTournament(Tournament $tournament): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count completed rounds for a tournament.
     */
    public function countCompletedRounds(Tournament $tournament): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.tournament = :tournament')
            ->andWhere('r.status = :status')
            ->setParameter('tournament', $tournament)
            ->setParameter('status', RoundStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find round with matches eagerly loaded.
     */
    public function findWithMatches(int $roundId): ?Round
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.matches', 'm')
            ->addSelect('m')
            ->leftJoin('m.player1', 'p1')
            ->addSelect('p1')
            ->leftJoin('m.player2', 'p2')
            ->addSelect('p2')
            ->leftJoin('p1.player', 'u1')
            ->addSelect('u1')
            ->leftJoin('p2.player', 'u2')
            ->addSelect('u2')
            ->where('r.id = :roundId')
            ->setParameter('roundId', $roundId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
