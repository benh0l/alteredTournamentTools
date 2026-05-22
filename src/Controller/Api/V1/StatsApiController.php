<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/stats')]
#[OA\Tag(name: 'Statistics', description: 'Statistiques globales de la plateforme')]
class StatsApiController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly UserRepository $userRepository,
        private readonly TournamentMatchRepository $matchRepository,
        private readonly RegistrationRepository $registrationRepository,
    ) {
    }

    #[Route('', name: 'api_v1_stats', methods: ['GET'])]
    #[OA\Get(
        summary: 'Statistiques globales',
        description: 'Retourne les statistiques globales de la plateforme',
    )]
    #[OA\Response(
        response: 200,
        description: 'Statistiques globales',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'tournaments', type: 'object', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'active', type: 'integer'),
                    new OA\Property(property: 'thisMonth', type: 'integer'),
                ]),
                new OA\Property(property: 'players', type: 'object', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                ]),
                new OA\Property(property: 'matches', type: 'object', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                ]),
                new OA\Property(property: 'registrations', type: 'object', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                ]),
                new OA\Property(property: 'formats', type: 'array', items: new OA\Items(type: 'object')),
            ]
        )
    )]
    public function global(): JsonResponse
    {
        $tournamentStats = [
            'total' => $this->tournamentRepository->countAll(),
            'active' => $this->tournamentRepository->countActive(),
            'thisMonth' => $this->tournamentRepository->countThisMonth(),
        ];

        $playerCount = $this->userRepository->count([]);
        $matchCount = $this->matchRepository->count([]);
        $registrationCount = $this->registrationRepository->count([]);

        // Get format distribution
        $formatDistribution = $this->tournamentRepository->countByFormat();

        return $this->json([
            'tournaments' => $tournamentStats,
            'players' => [
                'total' => $playerCount,
            ],
            'matches' => [
                'total' => $matchCount,
            ],
            'registrations' => [
                'total' => $registrationCount,
            ],
            'formats' => $formatDistribution,
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
