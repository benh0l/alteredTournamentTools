<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContactReport;
use App\Enum\ContactStatus;
use App\Repository\ContactReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/contact-reports')]
#[IsGranted('ROLE_ADMIN')]
class AdminContactReportController extends AbstractController
{
    public function __construct(
        private readonly ContactReportRepository $contactReportRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_contact_reports')]
    public function index(Request $request): Response
    {
        $statusFilter = $request->query->get('status');
        $page = $request->query->getInt('page', 1);

        $status = null;
        if ($statusFilter && ContactStatus::tryFrom($statusFilter)) {
            $status = ContactStatus::from($statusFilter);
        }

        $result = $this->contactReportRepository->searchReports($status, $page, 20);
        $newCount = $this->contactReportRepository->countNew();

        return $this->render('admin/contact/index.html.twig', [
            'reports' => $result['reports'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / 20),
            'statusFilter' => $statusFilter,
            'newCount' => $newCount,
            'statuses' => ContactStatus::cases(),
        ]);
    }

    #[Route('/{id}', name: 'admin_contact_report_view', methods: ['GET'])]
    public function view(ContactReport $report): Response
    {
        // Mark as read if new
        if ($report->getStatus() === ContactStatus::NEW) {
            $report->setStatus(ContactStatus::READ);
            $this->entityManager->flush();
        }

        return $this->render('admin/contact/view.html.twig', [
            'report' => $report,
            'statuses' => ContactStatus::cases(),
        ]);
    }

    #[Route('/{id}/status', name: 'admin_contact_report_status', methods: ['POST'])]
    public function updateStatus(ContactReport $report, Request $request): JsonResponse
    {
        $newStatus = $request->request->get('status');

        if (!$newStatus || !ContactStatus::tryFrom($newStatus)) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('contact.admin.error.invalid_status'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $report->setStatus(ContactStatus::from($newStatus));
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => $this->translator->trans('contact.admin.success.status_updated'),
            'status' => $newStatus,
            'statusLabel' => $this->translator->trans(ContactStatus::from($newStatus)->getLabel()),
            'statusColor' => ContactStatus::from($newStatus)->getColor(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_contact_report_delete', methods: ['POST'])]
    public function delete(ContactReport $report, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-report-' . $report->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('contact.admin.error.invalid_token'));

            return $this->redirectToRoute('admin_contact_reports');
        }

        $this->entityManager->remove($report);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('contact.admin.success.deleted'));

        return $this->redirectToRoute('admin_contact_reports');
    }
}
