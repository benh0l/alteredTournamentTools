<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\SystemAlert;
use App\Enum\AlertSeverity;
use App\Enum\AlertType;
use App\Repository\SystemAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * System health monitoring service (FR75).
 *
 * Performs health checks and creates alerts for system issues.
 */
class SystemHealthService
{
    private const ERROR_RATE_THRESHOLD = 1.0; // 1%
    private const RESPONSE_TIME_THRESHOLD = 3.0; // 3 seconds
    private const PAIRING_FAILURE_THRESHOLD = 3; // 3 failures in last hour

    public function __construct(
        private readonly SystemAlertRepository $alertRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LogReaderService $logReader,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $mercureHubUrl,
    ) {
    }

    /**
     * Get all active alerts.
     *
     * @return SystemAlert[]
     */
    public function getActiveAlerts(): array
    {
        return $this->alertRepository->findActiveAlerts();
    }

    /**
     * Get active alert counts by severity.
     *
     * @return array<string, int>
     */
    public function getAlertCounts(): array
    {
        return $this->alertRepository->countActiveBySeverity();
    }

    /**
     * Run all health checks.
     *
     * @return SystemAlert[] New alerts created
     */
    public function runAllChecks(): array
    {
        $newAlerts = [];

        $alert = $this->checkErrorRate();
        if ($alert !== null) {
            $newAlerts[] = $alert;
        }

        $alert = $this->checkMercureStatus();
        if ($alert !== null) {
            $newAlerts[] = $alert;
        }

        $alert = $this->checkPairingFailures();
        if ($alert !== null) {
            $newAlerts[] = $alert;
        }

        // Flush all new alerts
        if (!empty($newAlerts)) {
            $this->entityManager->flush();
        }

        return $newAlerts;
    }

    /**
     * Check error rate from logs.
     */
    public function checkErrorRate(): ?SystemAlert
    {
        // Skip if active alert already exists
        if ($this->alertRepository->hasActiveAlertOfType(AlertType::ERROR_RATE)) {
            return null;
        }

        try {
            $result = $this->logReader->readLogs('error', null, null, null, 1, 100);
            $errorCount = $result['total'];

            // Estimate total requests from info logs
            $infoResult = $this->logReader->readLogs('info', null, null, null, 1, 1000);
            $totalRequests = max($infoResult['total'], 1);

            if ($totalRequests < 10) {
                // Not enough data
                return null;
            }

            $errorRate = ($errorCount / $totalRequests) * 100;

            if ($errorRate > self::ERROR_RATE_THRESHOLD) {
                $alert = new SystemAlert(
                    AlertType::ERROR_RATE,
                    $errorRate > 5.0 ? AlertSeverity::CRITICAL : AlertSeverity::WARNING,
                    sprintf('Taux d\'erreur eleve: %.2f%% (seuil: %.1f%%)', $errorRate, self::ERROR_RATE_THRESHOLD)
                );

                $alert->setDetails([
                    'error_count' => $errorCount,
                    'total_requests' => $totalRequests,
                    'error_rate' => round($errorRate, 2),
                    'threshold' => self::ERROR_RATE_THRESHOLD,
                ]);

                $this->entityManager->persist($alert);
                $this->logger->warning('System health alert: High error rate', [
                    'error_rate' => $errorRate,
                ]);

                return $alert;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error checking error rate', ['exception' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Check Mercure hub status.
     */
    public function checkMercureStatus(): ?SystemAlert
    {
        // Skip if active alert already exists
        if ($this->alertRepository->hasActiveAlertOfType(AlertType::MERCURE_DOWN)) {
            return null;
        }

        if (empty($this->mercureHubUrl)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $this->mercureHubUrl, [
                'timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();

            // Mercure hub returns 200 or 401 (requires auth) when healthy
            if ($statusCode === 200 || $statusCode === 401) {
                // Auto-resolve existing alert if Mercure is back
                $existingAlert = $this->alertRepository->findActiveByType(AlertType::MERCURE_DOWN);
                if ($existingAlert !== null) {
                    // Could implement auto-acknowledge here
                }

                return null;
            }

            // Unexpected status code
            $alert = new SystemAlert(
                AlertType::MERCURE_DOWN,
                AlertSeverity::CRITICAL,
                'Le hub Mercure ne repond pas correctement'
            );

            $alert->setDetails([
                'url' => $this->mercureHubUrl,
                'status_code' => $statusCode,
            ]);

            $this->entityManager->persist($alert);
            $this->logger->critical('System health alert: Mercure hub not responding', [
                'url' => $this->mercureHubUrl,
                'status_code' => $statusCode,
            ]);

            return $alert;
        } catch (\Exception $e) {
            $alert = new SystemAlert(
                AlertType::MERCURE_DOWN,
                AlertSeverity::CRITICAL,
                'Le hub Mercure est injoignable'
            );

            $alert->setDetails([
                'url' => $this->mercureHubUrl,
                'error' => $e->getMessage(),
            ]);

            $this->entityManager->persist($alert);
            $this->logger->critical('System health alert: Mercure hub unreachable', [
                'url' => $this->mercureHubUrl,
                'error' => $e->getMessage(),
            ]);

            return $alert;
        }
    }

    /**
     * Check for Swiss pairing failures in logs.
     */
    public function checkPairingFailures(): ?SystemAlert
    {
        // Skip if active alert already exists
        if ($this->alertRepository->hasActiveAlertOfType(AlertType::PAIRING_FAILURE)) {
            return null;
        }

        try {
            // Search for pairing failure messages in error logs
            $result = $this->logReader->readLogs('error', null, null, null, 1, 100);

            $pairingFailures = 0;
            $failureDetails = [];

            foreach ($result['entries'] as $entry) {
                $message = $entry['message'] ?? '';
                if (
                    stripos($message, 'pairing') !== false ||
                    stripos($message, 'appariement') !== false
                ) {
                    $pairingFailures++;
                    $failureDetails[] = [
                        'timestamp' => $entry['datetime'] ?? null,
                        'message' => substr($message, 0, 100),
                    ];
                }
            }

            if ($pairingFailures >= self::PAIRING_FAILURE_THRESHOLD) {
                $alert = new SystemAlert(
                    AlertType::PAIRING_FAILURE,
                    AlertSeverity::CRITICAL,
                    sprintf('Echecs d\'appariement detectes: %d erreurs recentes', $pairingFailures)
                );

                $alert->setDetails([
                    'failure_count' => $pairingFailures,
                    'threshold' => self::PAIRING_FAILURE_THRESHOLD,
                    'recent_failures' => array_slice($failureDetails, 0, 5),
                ]);

                $this->entityManager->persist($alert);
                $this->logger->critical('System health alert: Pairing failures detected', [
                    'failure_count' => $pairingFailures,
                ]);

                return $alert;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error checking pairing failures', ['exception' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Create a custom alert.
     */
    public function createAlert(
        AlertType $type,
        AlertSeverity $severity,
        string $message,
        ?array $details = null
    ): SystemAlert {
        $alert = new SystemAlert($type, $severity, $message);

        if ($details !== null) {
            $alert->setDetails($details);
        }

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        return $alert;
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledgeAlert(SystemAlert $alert, \App\Entity\User $admin): void
    {
        $alert->acknowledge($admin);
        $this->entityManager->flush();

        $this->logger->info('Alert acknowledged', [
            'alert_id' => $alert->getId(),
            'alert_type' => $alert->getType()->value,
            'admin_id' => $admin->getId(),
        ]);
    }

    /**
     * Cleanup old acknowledged alerts.
     */
    public function cleanupOldAlerts(int $daysOld = 30): int
    {
        $count = $this->alertRepository->deleteOldAcknowledged($daysOld);

        if ($count > 0) {
            $this->logger->info('Cleaned up old alerts', ['count' => $count, 'days_old' => $daysOld]);
        }

        return $count;
    }
}
