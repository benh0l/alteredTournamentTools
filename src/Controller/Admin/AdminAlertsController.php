<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SystemAlert;
use App\Enum\AlertSeverity;
use App\Enum\AlertType;
use App\Repository\SystemAlertRepository;
use App\Service\Admin\SystemHealthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin system alerts controller (FR75).
 */
#[Route('/admin/alerts')]
#[IsGranted('ROLE_ADMIN')]
class AdminAlertsController extends AbstractController
{
    public function __construct(
        private readonly SystemHealthService $healthService,
        private readonly SystemAlertRepository $alertRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_alerts')]
    public function index(Request $request): Response
    {
        $typeValue = $request->query->get('type');
        $severityValue = $request->query->get('severity');
        $statusValue = $request->query->get('status');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $type = $typeValue ? AlertType::tryFrom($typeValue) : null;
        $severity = $severityValue ? AlertSeverity::tryFrom($severityValue) : null;

        $active = null;
        if ($statusValue === 'active') {
            $active = true;
        } elseif ($statusValue === 'acknowledged') {
            $active = false;
        }

        $result = $this->alertRepository->findWithFilters(
            $type,
            $severity,
            $active,
            $page,
            $limit
        );

        $alerts = $result['alerts'];
        $total = $result['total'];
        $totalPages = (int) ceil($total / $limit);

        // Get alert counts for quick stats
        $alertCounts = $this->healthService->getAlertCounts();

        return $this->render('admin/alerts/index.html.twig', [
            'alerts' => $alerts,
            'alertCounts' => $alertCounts,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'filters' => [
                'type' => $typeValue,
                'severity' => $severityValue,
                'status' => $statusValue,
            ],
            'alertTypes' => AlertType::cases(),
            'alertSeverities' => AlertSeverity::cases(),
        ]);
    }

    #[Route('/{id}', name: 'admin_alert_view', requirements: ['id' => '\d+'])]
    public function view(int $id): Response
    {
        $alert = $this->alertRepository->find($id);

        if (!$alert) {
            throw $this->createNotFoundException('Alert not found');
        }

        return $this->render('admin/alerts/view.html.twig', [
            'alert' => $alert,
        ]);
    }

    #[Route('/{id}/acknowledge', name: 'admin_alert_acknowledge', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function acknowledge(int $id, Request $request): Response
    {
        $alert = $this->alertRepository->find($id);

        if (!$alert) {
            throw $this->createNotFoundException('Alert not found');
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_alert_acknowledge', $submittedToken)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $this->healthService->acknowledgeAlert($alert, $this->getUser());

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', $this->translator->trans('flash.success.alert_acknowledged'));

        return $this->redirectToRoute('admin_alerts');
    }

    #[Route('/run-checks', name: 'admin_alerts_run_checks', methods: ['POST'])]
    public function runChecks(Request $request): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_run_health_checks', $submittedToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $newAlerts = $this->healthService->runAllChecks();

        if (count($newAlerts) > 0) {
            $this->addFlash('warning', sprintf('%d nouvelle(s) alerte(s) detectee(s).', count($newAlerts)));
        } else {
            $this->addFlash('success', $this->translator->trans('flash.success.alert_check_complete'));
        }

        return $this->redirectToRoute('admin_alerts');
    }

    #[Route('/active', name: 'admin_alerts_active', methods: ['GET'])]
    public function activeAlerts(): JsonResponse
    {
        $alerts = $this->healthService->getActiveAlerts();

        $data = array_map(fn (SystemAlert $alert) => [
            'id' => $alert->getId(),
            'type' => $alert->getType()->value,
            'typeLabel' => $this->translator->trans($alert->getType()->getLabel()),
            'severity' => $alert->getSeverity()->value,
            'severityLabel' => $this->translator->trans($alert->getSeverity()->getLabel()),
            'message' => $alert->getMessage(),
            'createdAt' => $alert->getCreatedAt()->format('c'),
        ], $alerts);

        return new JsonResponse([
            'alerts' => $data,
            'counts' => $this->healthService->getAlertCounts(),
        ]);
    }
}
