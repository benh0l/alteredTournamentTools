<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\TournamentFormat;
use App\Form\TournamentSearchType;
use App\Repository\TournamentRepository;
use App\Service\GeocodingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for tournament search functionality (FR59, FR60, FR61).
 *
 * FR59: Players search tournaments by city/postal code with radius.
 * FR60: Players filter by format and date.
 * FR61: Players view results in List or Map view.
 */
#[Route('/tournaments')]
final class TournamentSearchController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly GeocodingService $geocodingService,
    ) {
    }

    /**
     * Tournament search page (FR59).
     */
    #[Route('/search', name: 'tournament_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $form = $this->createForm(TournamentSearchType::class);
        $form->handleRequest($request);

        $results = [];
        $searchPerformed = false;
        $searchLocation = null;
        $searchCoordinates = null;
        $errorMessage = null;

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $searchPerformed = true;

            // Get coordinates - from form data or geocoding
            $lat = null;
            $lng = null;

            if (!empty($data['lat']) && !empty($data['lng'])) {
                // Use provided coordinates (from previous search)
                $lat = (float) $data['lat'];
                $lng = (float) $data['lng'];
                $searchLocation = $data['location'] ?? '';
            } elseif (!empty($data['location'])) {
                // Geocode the location
                $searchLocation = $data['location'];
                $coordinates = $this->geocodingService->geocode($searchLocation);

                if ($coordinates !== null) {
                    $lat = $coordinates['lat'];
                    $lng = $coordinates['lng'];
                } else {
                    $errorMessage = 'Lieu non trouve. Verifiez l\'orthographe ou essayez une autre adresse.';
                }
            }

            if ($lat !== null && $lng !== null) {
                $searchCoordinates = ['lat' => $lat, 'lng' => $lng];

                // Build date range filters
                $dateFrom = null;
                $dateTo = null;

                if (!empty($data['dateRange'])) {
                    [$dateFrom, $dateTo] = $this->calculateDateRange(
                        $data['dateRange'],
                        $data['dateFrom'] ?? null,
                        $data['dateTo'] ?? null
                    );
                }

                // Get format filter
                $format = null;
                if (!empty($data['format'])) {
                    $format = TournamentFormat::tryFrom($data['format']);
                }

                // Search tournaments
                $results = $this->tournamentRepository->findByLocation(
                    $lat,
                    $lng,
                    (float) ($data['radius'] ?? 50),
                    $format,
                    $dateFrom,
                    $dateTo
                );
            }
        }

        // For AJAX requests, return partial
        if ($request->isXmlHttpRequest()) {
            return $this->render('tournament/_search_results.html.twig', [
                'results' => $results,
                'search_performed' => $searchPerformed,
                'search_location' => $searchLocation,
                'search_coordinates' => $searchCoordinates,
            ]);
        }

        return $this->render('tournament/search.html.twig', [
            'form' => $form,
            'results' => $results,
            'search_performed' => $searchPerformed,
            'search_location' => $searchLocation,
            'search_coordinates' => $searchCoordinates,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Calculate date range from preset or custom dates.
     *
     * @return array{0: \DateTimeImmutable|null, 1: \DateTimeImmutable|null}
     */
    private function calculateDateRange(
        string $range,
        ?\DateTimeInterface $customFrom,
        ?\DateTimeInterface $customTo
    ): array {
        $today = new \DateTimeImmutable('today');

        return match ($range) {
            'today' => [$today, $today],
            'week' => [
                $today->modify('monday this week'),
                $today->modify('sunday this week'),
            ],
            'month' => [
                $today->modify('first day of this month'),
                $today->modify('last day of this month'),
            ],
            'custom' => [
                $customFrom instanceof \DateTimeInterface
                    ? \DateTimeImmutable::createFromInterface($customFrom)
                    : null,
                $customTo instanceof \DateTimeInterface
                    ? \DateTimeImmutable::createFromInterface($customTo)
                    : null,
            ],
            default => [null, null],
        };
    }
}
