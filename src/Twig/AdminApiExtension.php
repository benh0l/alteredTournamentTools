<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ApiAccessRequestRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for admin API access management.
 */
class AdminApiExtension extends AbstractExtension
{
    public function __construct(
        private readonly ApiAccessRequestRepository $requestRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_pending_api_requests_count', $this->getPendingApiRequestsCount(...)),
        ];
    }

    /**
     * Get count of pending API access requests (only for admins).
     */
    public function getPendingApiRequestsCount(): int
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return 0;
        }

        try {
            return $this->requestRepository->countPending();
        } catch (\Exception) {
            return 0;
        }
    }
}
