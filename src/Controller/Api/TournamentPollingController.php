<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use App\Repository\TournamentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Fallback polling endpoint when SSE fails (NFR24).
 *
 * This controller provides a REST endpoint for clients that cannot
 * maintain SSE connections to receive tournament updates via polling.
 */
#[Route('/api/tournaments')]
final class TournamentPollingController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
    ) {
    }

    /**
     * Get recent updates for a tournament.
     *
     * This is the fallback for SSE failure (NFR24).
     * Returns recent updates from cache/database.
     */
    #[Route('/{id}/updates', name: 'api_tournament_updates', methods: ['GET'])]
    public function getUpdates(int $id): JsonResponse
    {
        $tournament = $this->getTournamentOrFail($id);
        if ($tournament instanceof JsonResponse) {
            return $tournament;
        }

        // TODO: Implement actual update retrieval from cache/database
        return $this->json([
            'tournamentId' => $id,
            'updates' => [],
            'lastUpdate' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Get current standings for a tournament.
     */
    #[Route('/{id}/standings', name: 'api_tournament_standings', methods: ['GET'])]
    public function getStandings(int $id): JsonResponse
    {
        $tournament = $this->getTournamentOrFail($id);
        if ($tournament instanceof JsonResponse) {
            return $tournament;
        }

        // TODO: Implement actual standings retrieval
        return $this->json([
            'tournamentId' => $id,
            'standings' => [],
            'lastUpdate' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Get current timer status for a tournament.
     */
    #[Route('/{id}/timer', name: 'api_tournament_timer', methods: ['GET'])]
    public function getTimer(int $id): JsonResponse
    {
        $tournament = $this->getTournamentOrFail($id);
        if ($tournament instanceof JsonResponse) {
            return $tournament;
        }

        // TODO: Implement actual timer retrieval
        return $this->json([
            'tournamentId' => $id,
            'remainingSeconds' => 0,
            'isRunning' => false,
            'lastUpdate' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Verify tournament exists and user has access to view it.
     *
     * @return Tournament|JsonResponse Returns Tournament if accessible, JsonResponse error otherwise
     */
    private function getTournamentOrFail(int $id): Tournament|JsonResponse
    {
        $tournament = $this->tournamentRepository->find($id);

        if (!$tournament) {
            return $this->json(['error' => 'Tournament not found'], Response::HTTP_NOT_FOUND);
        }

        // Check visibility: private tournaments require authentication and ownership/registration
        if ($tournament->getVisibility() === TournamentVisibility::PRIVATE) {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
            }

            // Allow access to organizer or registered players
            $isOrganizer = $tournament->getOrganizer() === $user;
            $isRegistered = $tournament->isPlayerRegistered($user);

            if (!$isOrganizer && !$isRegistered) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }
        }

        return $tournament;
    }
}
