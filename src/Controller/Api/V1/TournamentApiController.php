<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Tournament;
use App\Enum\TournamentFormat;
use App\Enum\TournamentVisibility;
use App\Repository\TournamentRepository;
use App\Service\GeocodingService;
use App\Service\StandingsService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/tournaments')]
#[OA\Tag(name: 'Tournaments', description: 'Operations sur les tournois')]
class TournamentApiController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly StandingsService $standingsService,
        private readonly GeocodingService $geocodingService,
    ) {
    }

    #[Route('', name: 'api_v1_tournaments_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des tournois publics',
        description: 'Retourne la liste des tournois publics avec filtres optionnels',
    )]
    #[OA\Parameter(name: 'format', description: 'Filtrer par format de jeu', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'dateFrom', description: 'Date de debut (YYYY-MM-DD)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'dateTo', description: 'Date de fin (YYYY-MM-DD)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'limit', description: 'Nombre maximum de resultats (max 100)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50))]
    #[OA\Parameter(name: 'offset', description: 'Decalage pour la pagination', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(
        response: 200,
        description: 'Liste des tournois',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'tournaments', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'limit', type: 'integer'),
                new OA\Property(property: 'offset', type: 'integer'),
            ]
        )
    )]
    public function list(Request $request): JsonResponse
    {
        $limit = min((int) $request->query->get('limit', 50), 100);
        $offset = (int) $request->query->get('offset', 0);

        $tournaments = $this->tournamentRepository->findPublicTournaments();

        // Apply pagination
        $total = count($tournaments);
        $tournaments = array_slice($tournaments, $offset, $limit);

        $data = array_map(fn (Tournament $t) => $this->serializeTournament($t), $tournaments);

        return $this->json([
            'tournaments' => $data,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    #[Route('/upcoming', name: 'api_v1_tournaments_upcoming', methods: ['GET'])]
    #[OA\Get(
        summary: 'Tournois publics a venir',
        description: 'Retourne la liste des tournois publics a venir (date >= aujourd\'hui)',
    )]
    #[OA\Parameter(name: 'limit', description: 'Nombre maximum de resultats (max 100)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50))]
    #[OA\Parameter(name: 'offset', description: 'Decalage pour la pagination', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(
        response: 200,
        description: 'Liste des tournois a venir',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'tournaments', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'limit', type: 'integer'),
                new OA\Property(property: 'offset', type: 'integer'),
            ]
        )
    )]
    public function upcoming(Request $request): JsonResponse
    {
        $limit = min((int) $request->query->get('limit', 50), 100);
        $offset = (int) $request->query->get('offset', 0);

        $tournaments = $this->tournamentRepository->findUpcomingPublicTournaments();

        $total = count($tournaments);
        $tournaments = array_slice($tournaments, $offset, $limit);

        $data = array_map(fn (Tournament $t) => $this->serializeTournament($t), $tournaments);

        return $this->json([
            'tournaments' => $data,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    #[Route('/search', name: 'api_v1_tournaments_search', methods: ['POST'])]
    #[OA\Post(
        summary: 'Recherche de tournois avec filtres',
        description: 'Recherche de tournois publics avec filtres optionnels (localisation, format, dates, etc.)',
    )]
    #[OA\RequestBody(
        description: 'Filtres de recherche',
        required: false,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'location', type: 'string', description: 'Ville ou code postal (geocode automatiquement)'),
                new OA\Property(property: 'latitude', type: 'number', description: 'Latitude (alternative a location)'),
                new OA\Property(property: 'longitude', type: 'number', description: 'Longitude (alternative a location)'),
                new OA\Property(property: 'radius', type: 'integer', description: 'Rayon de recherche en km (defaut: 50, max: 1000)'),
                new OA\Property(property: 'format', type: 'string', description: 'Format de tournoi'),
                new OA\Property(property: 'dateFrom', type: 'string', format: 'date', description: 'Date de debut (YYYY-MM-DD)'),
                new OA\Property(property: 'dateTo', type: 'string', format: 'date', description: 'Date de fin (YYYY-MM-DD)'),
                new OA\Property(property: 'isTumult', type: 'boolean', description: 'Filtrer les tournois Tumult'),
                new OA\Property(property: 'isSeasonFinalsQualifier', type: 'boolean', description: 'Filtrer les qualificatifs de finale de saison'),
                new OA\Property(property: 'limit', type: 'integer', description: 'Nombre maximum de resultats (max 100, defaut: 50)'),
                new OA\Property(property: 'offset', type: 'integer', description: 'Decalage pour la pagination (defaut: 0)'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Resultats de recherche',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'tournaments', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'limit', type: 'integer'),
                new OA\Property(property: 'offset', type: 'integer'),
                new OA\Property(property: 'filters', type: 'object', description: 'Filtres appliques'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Parametre invalide')]
    public function search(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        // Pagination
        $limit = min((int) ($data['limit'] ?? 50), 100);
        $offset = max((int) ($data['offset'] ?? 0), 0);

        // Parse filters
        $location = isset($data['location']) ? trim((string) $data['location']) : null;
        $latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $radius = min(max((float) ($data['radius'] ?? 50), 1), 1000);

        // Format filter
        $format = null;
        if (isset($data['format']) && !empty($data['format'])) {
            $formatValue = (string) $data['format'];
            $format = TournamentFormat::tryFrom($formatValue);
            if ($format === null) {
                return $this->json([
                    'error' => 'invalid_parameter',
                    'message' => sprintf('Format invalide: %s', $formatValue),
                    'valid_formats' => array_map(fn (TournamentFormat $f) => $f->value, TournamentFormat::cases()),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Date filters
        $dateFrom = null;
        $dateTo = null;
        if (isset($data['dateFrom']) && !empty($data['dateFrom'])) {
            try {
                $dateFrom = new \DateTimeImmutable($data['dateFrom']);
            } catch (\Exception) {
                return $this->json([
                    'error' => 'invalid_parameter',
                    'message' => 'Format de date invalide pour dateFrom (attendu: YYYY-MM-DD)',
                ], Response::HTTP_BAD_REQUEST);
            }
        }
        if (isset($data['dateTo']) && !empty($data['dateTo'])) {
            try {
                $dateTo = new \DateTimeImmutable($data['dateTo']);
            } catch (\Exception) {
                return $this->json([
                    'error' => 'invalid_parameter',
                    'message' => 'Format de date invalide pour dateTo (attendu: YYYY-MM-DD)',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Boolean filters
        $isTumult = isset($data['isTumult']) ? (bool) $data['isTumult'] : null;
        $isSeasonFinalsQualifier = isset($data['isSeasonFinalsQualifier']) ? (bool) $data['isSeasonFinalsQualifier'] : null;

        if ($dateFrom === null) {
            $dateFrom = new \DateTimeImmutable('today');
        }

        // Determine search mode: location-based or filtered
        $hasLocation = false;
        $geocodedLocation = null;

        // Priority: direct coordinates > geocoded location
        if ($latitude !== null && $longitude !== null) {
            $hasLocation = true;
        } elseif ($location !== null && $location !== '') {
            $coords = $this->geocodingService->geocode($location);
            if ($coords !== null) {
                $latitude = $coords['lat'];
                $longitude = $coords['lng'];
                $hasLocation = true;
                $geocodedLocation = $location;
            }
        }

        // Execute search
        if ($hasLocation) {
            $results = $this->tournamentRepository->findByLocation(
                $latitude,
                $longitude,
                $radius,
                $format,
                $dateFrom,
                $dateTo,
                $isTumult,
                $isSeasonFinalsQualifier
            );
        } else {
            $results = $this->tournamentRepository->findPublicTournamentsFiltered(
                $format,
                $dateFrom,
                $dateTo,
                $isTumult,
                $isSeasonFinalsQualifier
            );
        }

        // Apply pagination
        $total = count($results);
        $results = array_slice($results, $offset, $limit);

        // Serialize results
        $tournaments = array_map(function (array $result) {
            $serialized = $this->serializeTournament($result['tournament']);
            if ($result['distance'] !== null) {
                $serialized['distance'] = $result['distance'];
            }

            return $serialized;
        }, $results);

        // Build applied filters for response
        $appliedFilters = [];
        if ($hasLocation) {
            $appliedFilters['latitude'] = $latitude;
            $appliedFilters['longitude'] = $longitude;
            $appliedFilters['radius'] = $radius;
            if ($geocodedLocation !== null) {
                $appliedFilters['geocodedFrom'] = $geocodedLocation;
            }
        }
        if ($format !== null) {
            $appliedFilters['format'] = $format->value;
        }
        if ($dateFrom !== null) {
            $appliedFilters['dateFrom'] = $dateFrom->format('Y-m-d');
        }
        if ($dateTo !== null) {
            $appliedFilters['dateTo'] = $dateTo->format('Y-m-d');
        }
        if ($isTumult !== null) {
            $appliedFilters['isTumult'] = $isTumult;
        }
        if ($isSeasonFinalsQualifier !== null) {
            $appliedFilters['isSeasonFinalsQualifier'] = $isSeasonFinalsQualifier;
        }

        return $this->json([
            'tournaments' => $tournaments,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $appliedFilters,
        ]);
    }

    #[Route('/{id}', name: 'api_v1_tournaments_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Details d\'un tournoi',
        description: 'Retourne les details complets d\'un tournoi public',
    )]
    #[OA\Parameter(name: 'id', description: 'ID du tournoi', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Details du tournoi',
        content: new OA\JsonContent(type: 'object')
    )]
    #[OA\Response(response: 404, description: 'Tournoi non trouve')]
    #[OA\Response(response: 403, description: 'Tournoi non public')]
    public function show(Tournament $tournament): JsonResponse
    {
        if ($tournament->getVisibility() !== TournamentVisibility::PUBLIC) {
            return $this->json([
                'error' => 'forbidden',
                'message' => 'Ce tournoi n\'est pas public',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializeTournamentDetailed($tournament));
    }

    #[Route('/{id}/standings', name: 'api_v1_tournaments_standings', methods: ['GET'])]
    #[OA\Get(
        summary: 'Classement d\'un tournoi',
        description: 'Retourne le classement actuel des joueurs',
    )]
    #[OA\Parameter(name: 'id', description: 'ID du tournoi', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Classement du tournoi',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'standings', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'roundsCompleted', type: 'integer'),
            ]
        )
    )]
    public function standings(Tournament $tournament): JsonResponse
    {
        if ($tournament->getVisibility() !== TournamentVisibility::PUBLIC) {
            return $this->json([
                'error' => 'forbidden',
                'message' => 'Ce tournoi n\'est pas public',
            ], Response::HTTP_FORBIDDEN);
        }

        $standings = $this->standingsService->calculateStandings($tournament);

        $data = array_map(fn (array $standing, int $index) => [
            'rank' => $index + 1,
            'player' => [
                'id' => $standing['registration']->getPlayer()->getId(),
                'pseudo' => $standing['registration']->getPlayer()->getPseudo(),
            ],
            'points' => $standing['matchPoints'],
            'matchWins' => $standing['wins'],
            'matchLosses' => $standing['losses'],
            'matchDraws' => $standing['draws'],
            'opponentMatchWinPercentage' => $standing['opponentMatchWinPercentage'],
            'gameWinPercentage' => $standing['gameWinPercentage'],
        ], $standings, array_keys($standings));

        return $this->json([
            'standings' => $data,
            'roundsCompleted' => $tournament->getRoundsCount(),
        ]);
    }

    #[Route('/{id}/rounds', name: 'api_v1_tournaments_rounds', methods: ['GET'])]
    #[OA\Get(
        summary: 'Rondes d\'un tournoi',
        description: 'Retourne la liste des rondes avec leur statut',
    )]
    #[OA\Parameter(name: 'id', description: 'ID du tournoi', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Liste des rondes',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'rounds', type: 'array', items: new OA\Items(type: 'object')),
            ]
        )
    )]
    public function rounds(Tournament $tournament): JsonResponse
    {
        if ($tournament->getVisibility() !== TournamentVisibility::PUBLIC) {
            return $this->json([
                'error' => 'forbidden',
                'message' => 'Ce tournoi n\'est pas public',
            ], Response::HTTP_FORBIDDEN);
        }

        $rounds = $tournament->getRounds()->toArray();
        usort($rounds, fn ($a, $b) => $a->getRoundNumber() <=> $b->getRoundNumber());

        $data = array_map(fn ($round) => [
            'roundNumber' => $round->getRoundNumber(),
            'status' => $round->getStatus()->value,
            'isElimination' => $round->isEliminationRound(),
            'matchCount' => $round->getMatches()->count(),
            'completedMatches' => $round->getMatches()->filter(fn ($m) => $m->isCompleted())->count(),
            'startedAt' => $round->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $round->getCompletedAt()?->format(\DateTimeInterface::ATOM),
        ], $rounds);

        return $this->json([
            'rounds' => $data,
        ]);
    }

    #[Route('/{id}/rounds/{roundNumber}/matches', name: 'api_v1_tournaments_matches', methods: ['GET'])]
    #[OA\Get(
        summary: 'Matchs d\'une ronde',
        description: 'Retourne les matchs d\'une ronde specifique',
    )]
    #[OA\Parameter(name: 'id', description: 'ID du tournoi', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'roundNumber', description: 'Numero de la ronde', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Liste des matchs',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'matches', type: 'array', items: new OA\Items(type: 'object')),
            ]
        )
    )]
    public function matches(Tournament $tournament, int $roundNumber): JsonResponse
    {
        if ($tournament->getVisibility() !== TournamentVisibility::PUBLIC) {
            return $this->json([
                'error' => 'forbidden',
                'message' => 'Ce tournoi n\'est pas public',
            ], Response::HTTP_FORBIDDEN);
        }

        $round = null;
        foreach ($tournament->getRounds() as $r) {
            if ($r->getRoundNumber() === $roundNumber) {
                $round = $r;
                break;
            }
        }

        if ($round === null) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Ronde non trouvee',
            ], Response::HTTP_NOT_FOUND);
        }

        $matches = $round->getMatches()->toArray();
        usort($matches, fn ($a, $b) => $a->getTableNumber() <=> $b->getTableNumber());

        $data = array_map(fn ($match) => [
            'id' => $match->getId(),
            'tableNumber' => $match->getTableNumber(),
            'status' => $match->getStatus()->value,
            'isBye' => $match->isBye(),
            'player1' => $match->getPlayer1() ? [
                'id' => $match->getPlayer1()->getPlayer()->getId(),
                'pseudo' => $match->getPlayer1()->getPlayer()->getPseudo(),
            ] : null,
            'player2' => $match->getPlayer2() ? [
                'id' => $match->getPlayer2()->getPlayer()->getId(),
                'pseudo' => $match->getPlayer2()->getPlayer()->getPseudo(),
            ] : null,
            'result' => $match->isCompleted() ? $match->getResult() : null,
        ], $matches);

        return $this->json([
            'matches' => $data,
            'roundNumber' => $roundNumber,
            'roundStatus' => $round->getStatus()->value,
        ]);
    }

    private function serializeTournament(Tournament $tournament): array
    {
        $date = $tournament->getDate();
        $time = $tournament->getTime();
        if ($time !== null) {
            $date = $date->setTime((int) $time->format('H'), (int) $time->format('i'), (int) $time->format('s'));
        }

        return [
            'id' => $tournament->getId(),
            'name' => $tournament->getName(),
            'date' => $date->format(\DateTimeInterface::ATOM),
            'location' => $tournament->getLocation(),
            'format' => $tournament->getFormat()->value,
            'structure' => $tournament->getStructure()->value,
            'status' => $tournament->getStatus()->value,
            'playerCount' => $tournament->getRegistrations()->count(),
            'maxPlayers' => $tournament->getExpectedPlayers(),
            'isTumult' => $tournament->isTumult(),
            'isSeasonFinalsQualifier' => $tournament->isSeasonFinalsQualifier(),
            'organizer' => [
                'id' => $tournament->getOrganizer()->getId(),
                'pseudo' => $tournament->getOrganizer()->getPseudo(),
            ],
        ];
    }

    private function serializeTournamentDetailed(Tournament $tournament): array
    {
        $base = $this->serializeTournament($tournament);

        return array_merge($base, [
            'description' => $tournament->getDescription(),
            'swissMatchFormat' => $tournament->getSwissMatchFormat()->value,
            'eliminationMatchFormat' => $tournament->getEliminationMatchFormat()?->value,
            'swissRounds' => $tournament->getSwissRounds(),
            'topCutSize' => $tournament->getTopCutSize(),
            'roundsCount' => $tournament->getRoundsCount(),
            'isTumult' => $tournament->isTumult(),
            'isSeasonFinalsQualifier' => $tournament->isSeasonFinalsQualifier(),
            'checkInEnabled' => $tournament->isCheckInEnabled(),
            'latitude' => $tournament->getLatitude(),
            'longitude' => $tournament->getLongitude(),
            'createdAt' => $tournament->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'completedAt' => $tournament->getCompletedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
