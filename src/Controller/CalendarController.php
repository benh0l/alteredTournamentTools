<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Service\CalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for calendar (ICS) file downloads (FR64).
 */
#[Route('/tournaments')]
final class CalendarController extends AbstractController
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {
    }

    /**
     * Download tournament as ICS calendar file.
     */
    #[Route('/{id}/calendar.ics', name: 'tournament_calendar_ics', methods: ['GET'])]
    public function downloadIcs(Tournament $tournament): Response
    {
        $icsContent = $this->calendarService->generateICS($tournament);
        $filename = $this->calendarService->getFilename($tournament);

        $response = new Response($icsContent);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Cache-Control', 'no-cache, private');

        return $response;
    }
}
