<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\TournamentGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TournamentGroup>
 *
 * @method TournamentGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method TournamentGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method TournamentGroup[]    findAll()
 * @method TournamentGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TournamentGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TournamentGroup::class);
    }

    /**
     * Find all groups for a tournament, ordered by letter.
     *
     * @return TournamentGroup[]
     */
    public function findByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('g.letter', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a group by tournament and letter.
     */
    public function findByTournamentAndLetter(Tournament $tournament, string $letter): ?TournamentGroup
    {
        return $this->createQueryBuilder('g')
            ->where('g.tournament = :tournament')
            ->andWhere('g.letter = :letter')
            ->setParameter('tournament', $tournament)
            ->setParameter('letter', strtoupper($letter))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find a group with players eagerly loaded.
     */
    public function findWithPlayers(int $groupId): ?TournamentGroup
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.players', 'p')
            ->addSelect('p')
            ->leftJoin('p.player', 'u')
            ->addSelect('u')
            ->where('g.id = :groupId')
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all groups for a tournament with players eagerly loaded.
     *
     * @return TournamentGroup[]
     */
    public function findByTournamentWithPlayers(Tournament $tournament): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.players', 'p')
            ->addSelect('p')
            ->leftJoin('p.player', 'u')
            ->addSelect('u')
            ->where('g.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('g.letter', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the group containing a specific player registration.
     */
    public function findByPlayer(Tournament $tournament, Registration $registration): ?TournamentGroup
    {
        return $this->createQueryBuilder('g')
            ->join('g.players', 'p')
            ->where('g.tournament = :tournament')
            ->andWhere('p = :registration')
            ->setParameter('tournament', $tournament)
            ->setParameter('registration', $registration)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count groups for a tournament.
     */
    public function countByTournament(Tournament $tournament): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
