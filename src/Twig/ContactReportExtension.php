<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ContactReportRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ContactReportExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContactReportRepository $contactReportRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_new_contact_reports_count', $this->getNewContactReportsCount(...)),
        ];
    }

    public function getNewContactReportsCount(): int
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return 0;
        }

        try {
            return $this->contactReportRepository->countNew();
        } catch (\Exception) {
            return 0;
        }
    }
}
