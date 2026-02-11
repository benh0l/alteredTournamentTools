<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TournamentMatch>
 *
 * @method TournamentMatch|null find($id, $lockMode = null, $lockVersion = null)
 * @method TournamentMatch|null findOneBy(array $criteria, array $orderBy = null)
 * @method TournamentMatch[]    findAll()
 * @method TournamentMatch[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TournamentMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TournamentMatch::class);
    }

    /**
     * Find all matches for a round, ordered by table number.
     *
     * @return TournamentMatch[]
     */
    public function findByRound(Round $round): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.round = :round')
            ->setParameter('round', $round)
            ->orderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find matches for a round with player data eagerly loaded.
     *
     * @return TournamentMatch[]
     */
    public function findByRoundWithPlayers(Round $round): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.player1', 'p1')
            ->addSelect('p1')
            ->leftJoin('m.player2', 'p2')
            ->addSelect('p2')
            ->leftJoin('p1.player', 'u1')
            ->addSelect('u1')
            ->leftJoin('p2.player', 'u2')
            ->addSelect('u2')
            ->where('m.round = :round')
            ->setParameter('round', $round)
            ->orderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all matches for a registration across all rounds.
     *
     * @return TournamentMatch[]
     */
    public function findByPlayer(Registration $registration): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')
            ->addSelect('r')
            ->where('m.player1 = :registration OR m.player2 = :registration')
            ->setParameter('registration', $registration)
            ->orderBy('r.roundNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find ongoing matches (status = ONGOING or PENDING) for a round.
     *
     * @return TournamentMatch[]
     */
    public function findOngoingMatches(Round $round): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.round = :round')
            ->andWhere('m.status IN (:statuses)')
            ->setParameter('round', $round)
            ->setParameter('statuses', [MatchStatus::PENDING, MatchStatus::ONGOING])
            ->orderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find completed matches for a round.
     *
     * @return TournamentMatch[]
     */
    public function findCompletedMatches(Round $round): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.round = :round')
            ->andWhere('m.status = :status')
            ->setParameter('round', $round)
            ->setParameter('status', MatchStatus::COMPLETED)
            ->orderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find disputed matches for a tournament.
     *
     * @return TournamentMatch[]
     */
    public function findDisputes(Tournament $tournament): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')
            ->addSelect('r')
            ->leftJoin('m.player1', 'p1')
            ->addSelect('p1')
            ->leftJoin('m.player2', 'p2')
            ->addSelect('p2')
            ->where('r.tournament = :tournament')
            ->andWhere('m.status = :status')
            ->setParameter('tournament', $tournament)
            ->setParameter('status', MatchStatus::DISPUTE)
            ->orderBy('r.roundNumber', 'ASC')
            ->addOrderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find disputes for a specific round.
     *
     * @return TournamentMatch[]
     */
    public function findDisputesByRound(Round $round): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.round = :round')
            ->andWhere('m.status = :status')
            ->setParameter('round', $round)
            ->setParameter('status', MatchStatus::DISPUTE)
            ->orderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if two registrations have already played each other in this tournament.
     */
    public function havePlayedEachOther(Registration $player1, Registration $player2): bool
    {
        $count = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->leftJoin('m.round', 'r')
            ->where('r.tournament = :tournament')
            ->andWhere(
                '(m.player1 = :player1 AND m.player2 = :player2) OR (m.player1 = :player2 AND m.player2 = :player1)'
            )
            ->setParameter('tournament', $player1->getTournament())
            ->setParameter('player1', $player1)
            ->setParameter('player2', $player2)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Find all opponents a registration has faced.
     *
     * @return Registration[]
     */
    public function findOpponents(Registration $registration): array
    {
        $matches = $this->findByPlayer($registration);
        $opponents = [];

        foreach ($matches as $match) {
            if ($match->isByeMatch()) {
                continue;
            }

            $opponent = $match->getOpponent($registration);
            if ($opponent !== null) {
                $opponents[] = $opponent;
            }
        }

        return $opponents;
    }

    /**
     * Count wins for a registration.
     */
    public function countWins(Registration $registration): int
    {
        $matches = $this->findByPlayer($registration);
        $wins = 0;

        foreach ($matches as $match) {
            if ($match->isCompleted() && $match->getWinner() === $registration) {
                $wins++;
            }
        }

        return $wins;
    }

    /**
     * Count losses for a registration.
     */
    public function countLosses(Registration $registration): int
    {
        $matches = $this->findByPlayer($registration);
        $losses = 0;

        foreach ($matches as $match) {
            if ($match->isCompleted() && !$match->isByeMatch() && $match->getLoser() === $registration) {
                $losses++;
            }
        }

        return $losses;
    }

    /**
     * Check if a registration has already received a BYE in this tournament.
     */
    public function hasReceivedBye(Registration $registration): bool
    {
        $count = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->leftJoin('m.round', 'r')
            ->where('r.tournament = :tournament')
            ->andWhere('m.player1 = :registration')
            ->andWhere('m.isBye = true')
            ->setParameter('tournament', $registration->getTournament())
            ->setParameter('registration', $registration)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Find all matches with dispute history for a registration (player).
     *
     * Returns matches that have dispute_history not null, meaning
     * a dispute was created or submissions were tracked for validation.
     *
     * @return TournamentMatch[]
     */
    public function findMatchesWithDisputeHistory(Registration $registration): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')
            ->addSelect('r')
            ->where('(m.player1 = :registration OR m.player2 = :registration)')
            ->andWhere('m.disputeHistory IS NOT NULL')
            ->setParameter('registration', $registration)
            ->orderBy('r.roundNumber', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count matches where a player was involved in a dispute.
     *
     * A "disputed" match is one that went through the dispute resolution process.
     */
    public function countDisputesForPlayer(Registration $registration): int
    {
        $matches = $this->findMatchesWithDisputeHistory($registration);
        $count = 0;

        foreach ($matches as $match) {
            $history = $match->getDisputeHistory();
            if ($history === null) {
                continue;
            }

            // Check if there was a 'created' dispute event
            foreach ($history as $entry) {
                if ($entry['type'] === 'created' && ($entry['data']['reason'] ?? '') === 'conflicting_submissions') {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Find all disputed matches where a player was involved (across all tournaments).
     *
     * @return TournamentMatch[]
     */
    public function findDisputedMatchesForPlayer(Registration $registration): array
    {
        $matches = $this->findMatchesWithDisputeHistory($registration);

        return array_filter($matches, function (TournamentMatch $match) {
            $history = $match->getDisputeHistory();
            if ($history === null) {
                return false;
            }

            foreach ($history as $entry) {
                if ($entry['type'] === 'created' && ($entry['data']['reason'] ?? '') === 'conflicting_submissions') {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Find all completed matches for a tournament (FR58).
     *
     * @return TournamentMatch[]
     */
    public function findCompletedByTournament(Tournament $tournament): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')
            ->addSelect('r')
            ->leftJoin('m.player1', 'p1')
            ->addSelect('p1')
            ->leftJoin('m.player2', 'p2')
            ->addSelect('p2')
            ->where('r.tournament = :tournament')
            ->andWhere('m.status = :status')
            ->setParameter('tournament', $tournament)
            ->setParameter('status', MatchStatus::COMPLETED)
            ->orderBy('r.roundNumber', 'ASC')
            ->addOrderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count all pending disputes across all active tournaments (FR68).
     */
    public function countAllPendingDisputes(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.status = :status')
            ->setParameter('status', MatchStatus::DISPUTE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all completed matches for a specific group.
     *
     * @return TournamentMatch[]
     */
    public function findCompletedByGroup(\App\Entity\TournamentGroup $group): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')
            ->addSelect('r')
            ->leftJoin('m.player1', 'p1')
            ->addSelect('p1')
            ->leftJoin('m.player2', 'p2')
            ->addSelect('p2')
            ->where('m.group = :group')
            ->andWhere('m.status = :status')
            ->setParameter('group', $group)
            ->setParameter('status', MatchStatus::COMPLETED)
            ->orderBy('r.roundNumber', 'ASC')
            ->addOrderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all matches for a specific group.
     *
     * @return TournamentMatch[]
     */
    public function findByGroup(\App\Entity\TournamentGroup $group): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')
            ->addSelect('r')
            ->leftJoin('m.player1', 'p1')
            ->addSelect('p1')
            ->leftJoin('m.player2', 'p2')
            ->addSelect('p2')
            ->where('m.group = :group')
            ->setParameter('group', $group)
            ->orderBy('r.roundNumber', 'ASC')
            ->addOrderBy('m.tableNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all completed matches for a user across all tournaments.
     * Returns matches ordered by completion date (most recent first).
     *
     * @return TournamentMatch[]
     */
    public function findAllByUser(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')
            ->addSelect('r')
            ->leftJoin('r.tournament', 't')
            ->addSelect('t')
            ->leftJoin('m.player1', 'p1')
            ->addSelect('p1')
            ->leftJoin('p1.player', 'u1')
            ->addSelect('u1')
            ->leftJoin('m.player2', 'p2')
            ->addSelect('p2')
            ->leftJoin('p2.player', 'u2')
            ->addSelect('u2')
            ->where('u1 = :user OR u2 = :user')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MatchStatus::COMPLETED)
            ->orderBy('m.completedAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count total completed matches for a user.
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->leftJoin('m.player1', 'p1')
            ->leftJoin('p1.player', 'u1')
            ->leftJoin('m.player2', 'p2')
            ->leftJoin('p2.player', 'u2')
            ->where('u1 = :user OR u2 = :user')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MatchStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
