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

        // Check if search was performed (form submitted via GET - check for any query params)
        $hasSearchParams = $request->query->count() > 0;

        if ($hasSearchParams) {
            $searchPerformed = true;

            // Get values directly from request query (more reliable for GET forms)
            $location = $request->query->get('location', '');
            $radius = $request->query->get('radius', 50);
            $formatValue = $request->query->get('format', '');
            $dateFromStr = $request->query->get('dateFrom', '');
            $dateToStr = $request->query->get('dateTo', '');
            $latParam = $request->query->get('lat', '');
            $lngParam = $request->query->get('lng', '');

            // Parse date filters
            $dateFrom = null;
            $dateTo = null;

            if (!empty($dateFromStr)) {
                try {
                    $dateFrom = new \DateTimeImmutable($dateFromStr);
                } catch (\Exception $e) {
                    // Invalid date format, ignore
                }
            }

            if (!empty($dateToStr)) {
                try {
                    $dateTo = new \DateTimeImmutable($dateToStr);
                } catch (\Exception $e) {
                    // Invalid date format, ignore
                }
            }

            // Get format filter
            $format = null;
            if (!empty($formatValue)) {
                $format = TournamentFormat::tryFrom($formatValue);
            }

            // Get coordinates - from form data or geocoding
            $lat = null;
            $lng = null;

            if (!empty($latParam) && !empty($lngParam)) {
                // Use provided coordinates (from previous search)
                $lat = (float) $latParam;
                $lng = (float) $lngParam;
                $searchLocation = $location;
            } elseif (!empty($location)) {
                // Geocode the location
                $searchLocation = $location;
                $coordinates = $this->geocodingService->geocode($searchLocation);

                if ($coordinates !== null) {
                    $lat = $coordinates['lat'];
                    $lng = $coordinates['lng'];
                } else {
                    $errorMessage = 'Lieu non trouvé. Vérifiez l\'orthographe ou essayez une autre adresse.';
                }
            }

            if ($lat !== null && $lng !== null) {
                // Search with location
                $searchCoordinates = ['lat' => $lat, 'lng' => $lng];

                $results = $this->tournamentRepository->findByLocation(
                    $lat,
                    $lng,
                    (float) $radius,
                    $format,
                    $dateFrom,
                    $dateTo
                );
            } elseif ($errorMessage === null) {
                // Search without location (all public tournaments with optional filters)
                $results = $this->tournamentRepository->findPublicTournamentsFiltered(
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
}
