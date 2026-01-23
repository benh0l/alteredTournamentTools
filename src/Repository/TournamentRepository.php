<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tournament>
 */
class TournamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }

    /**
     * Find all tournaments organized by a specific user.
     *
     * @return Tournament[]
     */
    public function findByOrganizer(User $organizer): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.organizer = :organizer')
            ->setParameter('organizer', $organizer)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tournaments by status.
     *
     * @return Tournament[]
     */
    public function findByStatus(TournamentStatus $status): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.organizer', 'o')
            ->addSelect('o')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all public tournaments that are published or ongoing.
     *
     * @return Tournament[]
     */
    public function findPublicTournaments(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.organizer', 'o')
            ->addSelect('o')
            ->where('t.visibility = :visibility')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('visibility', TournamentVisibility::PUBLIC)
            ->setParameter('statuses', [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING])
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find public tournaments with optional filters (without location).
     *
     * @return array<int, array{tournament: Tournament, distance: null}>
     */
    public function findPublicTournamentsFiltered(
        ?TournamentFormat $format = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
        ?bool $isTumult = null,
        ?bool $isSeasonFinalsQualifier = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.organizer', 'o')
            ->addSelect('o')
            ->where('t.visibility = :visibility')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('visibility', TournamentVisibility::PUBLIC)
            ->setParameter('statuses', [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING]);

        if ($format !== null) {
            $qb->andWhere('t.format = :format')
                ->setParameter('format', $format);
        }

        if ($dateFrom !== null) {
            $qb->andWhere('t.date >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('t.date <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        if ($isTumult === true) {
            $qb->andWhere('t.isTumult = :isTumult')
                ->setParameter('isTumult', true);
        }

        if ($isSeasonFinalsQualifier === true) {
            $qb->andWhere('t.isSeasonFinalsQualifier = :isSeasonFinalsQualifier')
                ->setParameter('isSeasonFinalsQualifier', true);
        }

        $tournaments = $qb->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Return in same format as findByLocation for template compatibility
        return array_map(fn (Tournament $t) => [
            'tournament' => $t,
            'distance' => null,
        ], $tournaments);
    }

    /**
     * Find upcoming public tournaments (published, not yet started).
     *
     * @return Tournament[]
     */
    public function findUpcomingPublicTournaments(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.organizer', 'o')
            ->addSelect('o')
            ->where('t.visibility = :visibility')
            ->andWhere('t.status = :status')
            ->andWhere('t.date >= :today')
            ->setParameter('visibility', TournamentVisibility::PUBLIC)
            ->setParameter('status', TournamentStatus::PUBLISHED)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find ongoing tournaments (active tournaments).
     *
     * @return Tournament[]
     */
    public function findOngoingTournaments(): array
    {
        return $this->findByStatus(TournamentStatus::ONGOING);
    }

    /**
     * Find completed tournaments.
     *
     * @return Tournament[]
     */
    public function findCompletedTournaments(): array
    {
        return $this->findByStatus(TournamentStatus::COMPLETED);
    }

    /**
     * Find tournaments by location using Haversine formula (FR59).
     *
     * Returns tournaments within the specified radius from the given coordinates,
     * filtered by format and date range, ordered by distance.
     *
     * @param float                $lat        Center latitude
     * @param float                $lng        Center longitude
     * @param float                $radiusKm   Search radius in kilometers
     * @param TournamentFormat|null $format    Optional format filter
     * @param \DateTimeImmutable|null $dateFrom Optional date range start
     * @param \DateTimeImmutable|null $dateTo   Optional date range end
     * @param bool|null            $isTumult   Optional tumult filter
     * @param bool|null            $isSeasonFinalsQualifier Optional qualifier filter
     *
     * @return array<int, array{tournament: Tournament, distance: float}>
     */
    public function findByLocation(
        float $lat,
        float $lng,
        float $radiusKm = 50.0,
        ?TournamentFormat $format = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
        ?bool $isTumult = null,
        ?bool $isSeasonFinalsQualifier = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.organizer', 'o')
            ->addSelect('o')
            ->where('t.visibility = :visibility')
            ->andWhere('t.status IN (:statuses)')
            ->andWhere('t.latitude IS NOT NULL')
            ->andWhere('t.longitude IS NOT NULL')
            ->setParameter('visibility', TournamentVisibility::PUBLIC)
            ->setParameter('statuses', [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING]);

        // Format filter
        if ($format !== null) {
            $qb->andWhere('t.format = :format')
                ->setParameter('format', $format);
        }

        // Date range filters
        if ($dateFrom !== null) {
            $qb->andWhere('t.date >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('t.date <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        // Tournament type filters
        if ($isTumult === true) {
            $qb->andWhere('t.isTumult = :isTumult')
                ->setParameter('isTumult', true);
        }

        if ($isSeasonFinalsQualifier === true) {
            $qb->andWhere('t.isSeasonFinalsQualifier = :isSeasonFinalsQualifier')
                ->setParameter('isSeasonFinalsQualifier', true);
        }

        // Get all matching tournaments
        $tournaments = $qb->orderBy('t.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Calculate distances in PHP (more portable than SQL functions)
        $results = [];
        foreach ($tournaments as $tournament) {
            $distance = $this->calculateHaversineDistance(
                $lat,
                $lng,
                $tournament->getLatitude(),
                $tournament->getLongitude()
            );

            if ($distance <= $radiusKm) {
                $results[] = [
                    'tournament' => $tournament,
                    'distance' => round($distance, 1),
                ];
            }
        }

        // Sort by distance
        usort($results, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return $results;
    }

    /**
     * Calculate distance between two points using Haversine formula.
     *
     * @param float $lat1 First point latitude
     * @param float $lng1 First point longitude
     * @param float $lat2 Second point latitude
     * @param float $lng2 Second point longitude
     *
     * @return float Distance in kilometers
     */
    private function calculateHaversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * asin(sqrt($a));

        return $earthRadius * $c;
    }

    /**
     * Count tournaments by format (for statistics).
     *
     * @return array<string, int>
     */
    public function countByFormat(): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('t.format, COUNT(t.id) as count')
            ->where('t.visibility = :visibility')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('visibility', TournamentVisibility::PUBLIC)
            ->setParameter('statuses', [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING])
            ->groupBy('t.format')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['format']->value] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find all active tournaments (PUBLISHED or ONGOING) with details for admin dashboard.
     *
     * @return array<int, array{tournament: Tournament, playerCount: int, currentRound: int|null}>
     */
    public function findAllActiveWithDetails(): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.organizer', 'o')
            ->addSelect('o')
            ->leftJoin('t.rounds', 'r')
            ->addSelect('r')
            ->where('t.status IN (:statuses)')
            ->setParameter('statuses', [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING])
            ->orderBy('t.status', 'DESC')
            ->addOrderBy('t.date', 'ASC');

        $tournaments = $qb->getQuery()->getResult();

        $em = $this->getEntityManager();
        $results = [];

        foreach ($tournaments as $tournament) {
            // Count registrations for this tournament
            $playerCount = (int) $em->createQueryBuilder()
                ->select('COUNT(reg.id)')
                ->from('App\Entity\Registration', 'reg')
                ->where('reg.tournament = :tournament')
                ->setParameter('tournament', $tournament)
                ->getQuery()
                ->getSingleScalarResult();

            // Find current round number
            $currentRound = null;
            $rounds = $tournament->getRounds();
            if (!$rounds->isEmpty()) {
                $maxRound = 0;
                foreach ($rounds as $round) {
                    if ($round->getRoundNumber() > $maxRound) {
                        $maxRound = $round->getRoundNumber();
                    }
                }
                $currentRound = $maxRound;
            }

            $results[] = [
                'tournament' => $tournament,
                'playerCount' => $playerCount,
                'currentRound' => $currentRound,
            ];
        }

        return $results;
    }

    /**
     * Count all active tournaments.
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status IN (:statuses)')
            ->setParameter('statuses', [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count all tournaments (FR70).
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count tournaments created this month (FR70).
     */
    public function countThisMonth(): int
    {
        $startOfMonth = new \DateTimeImmutable('first day of this month midnight');
        $endOfMonth = new \DateTimeImmutable('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.createdAt >= :start')
            ->andWhere('t.createdAt <= :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get monthly tournament counts for the last N months (FR70).
     *
     * @return array<int, array{month: string, count: int}>
     */
    public function getMonthlyTournaments(int $months = 12): array
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
                 FROM tournaments
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
     * Find tournament with all details for admin inspector (FR73).
     */
    public function findWithAllDetails(int $id): ?Tournament
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.organizer', 'o')->addSelect('o')
            ->leftJoin('t.registrations', 'r')->addSelect('r')
            ->leftJoin('r.player', 'p')->addSelect('p')
            ->leftJoin('t.rounds', 'rd')->addSelect('rd')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get tournament size distribution (FR70).
     *
     * @return array<string, int>
     */
    public function getTournamentSizeDistribution(): array
    {
        $results = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT
                    CASE
                        WHEN player_count <= 8 THEN '1-8'
                        WHEN player_count <= 16 THEN '9-16'
                        WHEN player_count <= 32 THEN '17-32'
                        WHEN player_count <= 64 THEN '33-64'
                        ELSE '65+'
                    END as size_range,
                    COUNT(*) as count
                 FROM (
                     SELECT t.id, COUNT(r.id) as player_count
                     FROM tournaments t
                     LEFT JOIN registrations r ON r.tournament_id = t.id
                     GROUP BY t.id
                 ) sizes
                 GROUP BY size_range
                 ORDER BY size_range"
            )
            ->fetchAllAssociative();

        $distribution = [];
        foreach ($results as $row) {
            $distribution[$row['size_range']] = (int) $row['count'];
        }

        // Ensure all ranges exist
        foreach (['1-8', '9-16', '17-32', '33-64', '65+'] as $range) {
            if (!isset($distribution[$range])) {
                $distribution[$range] = 0;
            }
        }

        return $distribution;
    }
}
