<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for business metrics with caching (FR70).
 */
class MetricsService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly UserRepository $userRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Get all metrics at once (cached).
     *
     * @return array<string, mixed>
     */
    public function getAllMetrics(): array
    {
        return [
            'tournaments' => $this->getTournamentMetrics(),
            'players' => $this->getPlayerMetrics(),
            'organizers' => $this->getOrganizerMetrics(),
            'averageSize' => $this->getAverageTournamentSize(),
            'charts' => $this->getChartData(),
        ];
    }

    /**
     * Get tournament metrics (cached).
     *
     * @return array{total: int, thisMonth: int, lastMonth: int, trend: float}
     */
    public function getTournamentMetrics(): array
    {
        return $this->cache->get('admin.metrics.tournaments', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(['admin_metrics']);

            $total = $this->tournamentRepository->countAll();
            $thisMonth = $this->tournamentRepository->countThisMonth();

            // Calculate last month for trend
            $lastMonthStart = new \DateTimeImmutable('first day of last month midnight');
            $lastMonthEnd = new \DateTimeImmutable('last day of last month 23:59:59');
            $lastMonth = $this->countTournamentsInRange($lastMonthStart, $lastMonthEnd);

            $trend = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0;

            return [
                'total' => $total,
                'thisMonth' => $thisMonth,
                'lastMonth' => $lastMonth,
                'trend' => $trend,
            ];
        });
    }

    /**
     * Get player metrics (cached).
     *
     * @return array{total: int, unique: int, activeThisMonth: int, newThisMonth: int, trend: float}
     */
    public function getPlayerMetrics(): array
    {
        return $this->cache->get('admin.metrics.players', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(['admin_metrics']);

            $total = $this->userRepository->countAll();
            $unique = $this->userRepository->countUniquePlayers();
            $activeThisMonth = $this->userRepository->countActiveThisMonth();
            $newThisMonth = $this->userRepository->countThisMonth();

            // Calculate last month new users for trend
            $lastMonthStart = new \DateTimeImmutable('first day of last month midnight');
            $lastMonthEnd = new \DateTimeImmutable('last day of last month 23:59:59');
            $lastMonthNew = $this->countUsersInRange($lastMonthStart, $lastMonthEnd);

            $trend = $lastMonthNew > 0 ? round((($newThisMonth - $lastMonthNew) / $lastMonthNew) * 100, 1) : 0;

            return [
                'total' => $total,
                'unique' => $unique,
                'activeThisMonth' => $activeThisMonth,
                'newThisMonth' => $newThisMonth,
                'trend' => $trend,
            ];
        });
    }

    /**
     * Get organizer retention metrics (cached).
     *
     * @return array{total: int, retained: int, rate: float, trend: float}
     */
    public function getOrganizerMetrics(): array
    {
        return $this->cache->get('admin.metrics.organizers', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(['admin_metrics']);

            $total = $this->userRepository->countTotalOrganizers();
            $retained = $this->userRepository->countOrganizersWithMultipleTournaments();
            $rate = $total > 0 ? round(($retained / $total) * 100, 1) : 0;

            return [
                'total' => $total,
                'retained' => $retained,
                'rate' => $rate,
                'trend' => 0, // Trend calculation would need historical data
            ];
        });
    }

    /**
     * Get average tournament size (cached).
     *
     * @return array{average: float, trend: float}
     */
    public function getAverageTournamentSize(): array
    {
        return $this->cache->get('admin.metrics.average_size', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(['admin_metrics']);

            $average = $this->registrationRepository->getAveragePlayersPerTournament();

            return [
                'average' => $average,
                'trend' => 0, // Trend calculation would need historical data
            ];
        });
    }

    /**
     * Get chart data (cached).
     *
     * @return array{monthly: array, distribution: array}
     */
    public function getChartData(): array
    {
        return $this->cache->get('admin.metrics.charts', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(['admin_metrics']);

            return [
                'monthlyTournaments' => $this->tournamentRepository->getMonthlyTournaments(12),
                'monthlyUsers' => $this->userRepository->getMonthlyRegistrations(12),
                'sizeDistribution' => $this->tournamentRepository->getTournamentSizeDistribution(),
            ];
        });
    }

    /**
     * Invalidate all metrics cache.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete('admin.metrics.tournaments');
        $this->cache->delete('admin.metrics.players');
        $this->cache->delete('admin.metrics.organizers');
        $this->cache->delete('admin.metrics.average_size');
        $this->cache->delete('admin.metrics.charts');
    }

    /**
     * Count tournaments in a date range.
     */
    private function countTournamentsInRange(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return (int) $this->tournamentRepository
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.createdAt >= :start')
            ->andWhere('t.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users registered in a date range.
     */
    private function countUsersInRange(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return (int) $this->userRepository
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
