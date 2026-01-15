<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\SystemAlertRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for admin alerts (FR75).
 */
class AdminAlertExtension extends AbstractExtension
{
    public function __construct(
        private readonly SystemAlertRepository $alertRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_active_alerts_count', $this->getActiveAlertsCount(...)),
            new TwigFunction('admin_active_alerts', $this->getActiveAlerts(...)),
        ];
    }

    /**
     * Get count of active alerts (only for admins).
     */
    public function getActiveAlertsCount(): int
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return 0;
        }

        try {
            $counts = $this->alertRepository->countActiveBySeverity();

            return $counts['critical'] + $counts['warning'] + $counts['info'];
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Get active alerts (only for admins).
     *
     * @return array<\App\Entity\SystemAlert>
     */
    public function getActiveAlerts(): array
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return [];
        }

        try {
            return $this->alertRepository->findActiveAlerts();
        } catch (\Exception) {
            return [];
        }
    }
}
