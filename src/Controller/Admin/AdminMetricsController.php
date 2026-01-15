<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\MetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin metrics dashboard controller (FR70).
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminMetricsController extends AbstractController
{
    public function __construct(
        private readonly MetricsService $metricsService,
    ) {
    }

    #[Route('/metrics', name: 'admin_metrics')]
    public function index(): Response
    {
        $metrics = $this->metricsService->getAllMetrics();

        return $this->render('admin/metrics.html.twig', [
            'metrics' => $metrics,
        ]);
    }

    #[Route('/metrics/refresh', name: 'admin_metrics_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        $this->metricsService->invalidateCache();
        $metrics = $this->metricsService->getAllMetrics();

        return new JsonResponse([
            'success' => true,
            'metrics' => $metrics,
        ]);
    }

    #[Route('/metrics/api', name: 'admin_metrics_api', methods: ['GET'])]
    public function api(): JsonResponse
    {
        $metrics = $this->metricsService->getAllMetrics();

        return new JsonResponse($metrics);
    }
}
