<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    /**
     * Get recent updates for a tournament.
     *
     * This is the fallback for SSE failure (NFR24).
     * Returns recent updates from cache/database.
     */
    #[Route('/{id}/updates', name: 'api_tournament_updates', methods: ['GET'])]
    public function getUpdates(int $id): JsonResponse
    {
        // TODO: Implement actual update retrieval from cache/database
        // This will be implemented when tournament entities exist
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
        // TODO: Implement actual timer retrieval
        return $this->json([
            'tournamentId' => $id,
            'remainingSeconds' => 0,
            'isRunning' => false,
            'lastUpdate' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
