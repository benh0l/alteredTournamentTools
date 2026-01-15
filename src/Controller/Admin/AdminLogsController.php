<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\LogReaderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin logs viewer controller (FR69).
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminLogsController extends AbstractController
{
    public function __construct(
        private readonly LogReaderService $logReaderService,
    ) {
    }

    #[Route('/logs', name: 'admin_logs')]
    public function index(Request $request): Response
    {
        $level = $request->query->get('level');
        $keyword = $request->query->get('keyword');
        $page = $request->query->getInt('page', 1);
        $limit = 100;

        // Parse date filters
        $startDate = null;
        $endDate = null;
        $startDateStr = $request->query->get('start_date');
        $endDateStr = $request->query->get('end_date');

        if ($startDateStr) {
            $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $startDateStr);
            if ($startDate !== false) {
                $startDate = $startDate->setTime(0, 0, 0);
            } else {
                $startDate = null;
            }
        }

        if ($endDateStr) {
            $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', $endDateStr);
            if ($endDate !== false) {
                $endDate = $endDate->setTime(23, 59, 59);
            } else {
                $endDate = null;
            }
        }

        $result = $this->logReaderService->readLogs(
            $level,
            $startDate,
            $endDate,
            $keyword,
            $page,
            $limit
        );

        // Handle AJAX request for filter updates
        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/logs/_entries.html.twig', [
                'entries' => $result['entries'],
                'pagination' => [
                    'current' => $result['page'],
                    'total' => $result['total'],
                    'totalPages' => $result['totalPages'],
                    'limit' => $result['limit'],
                ],
            ]);
        }

        return $this->render('admin/logs.html.twig', [
            'entries' => $result['entries'],
            'pagination' => [
                'current' => $result['page'],
                'total' => $result['total'],
                'totalPages' => $result['totalPages'],
                'limit' => $result['limit'],
            ],
            'filters' => [
                'level' => $level,
                'keyword' => $keyword,
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
            ],
            'availableLevels' => $this->logReaderService->getAvailableLevels(),
            'availableFiles' => $this->logReaderService->getAvailableLogFiles(),
        ]);
    }

    #[Route('/logs/entry/{id}', name: 'admin_logs_entry', methods: ['GET'])]
    public function entry(int $id): JsonResponse
    {
        // This could be implemented to fetch a specific entry by line number
        // For now, return a not implemented response
        return new JsonResponse(['error' => 'Not implemented'], 501);
    }
}
