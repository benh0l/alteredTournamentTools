<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Tournament;
use App\Enum\MatchStatus;
use App\Repository\TournamentMatchRepository;
use App\Security\Voter\TournamentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API Controller for real-time dashboard updates.
 *
 * FR47: Dashboard automatically updates via push (SSE) without manual refresh.
 *
 * Provides two mechanisms:
 * - SSE stream endpoint for real-time push updates
 * - JSON endpoint for polling fallback (NFR24)
 *
 * Public tournaments (ONGOING/COMPLETED) are accessible to anyone.
 */
#[Route('/api/dashboard', requirements: ['id' => '\d+'])]
final class DashboardStreamController extends AbstractController
{
    public function __construct(
        private readonly TournamentMatchRepository $matchRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * SSE stream for real-time dashboard updates.
     *
     * Sends statistics updates every 5 seconds.
     * Client connects with EventSource and receives periodic updates.
     */
    #[Route('/{id}/stream', name: 'api_dashboard_stream', methods: ['GET'])]
    public function sseStream(Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::VIEW_DASHBOARD, $tournament);

        $response = new StreamedResponse(function () use ($tournament) {
            // Disable output buffering completely
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Release session lock to allow other requests from same user
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            // Ensure script stops when client disconnects
            ignore_user_abort(false);

            // Set connection timeout
            set_time_limit(0);

            $lastState = '';
            $iterations = 0;
            $maxIterations = 360; // 30 minutes at 5-second intervals

            while ($iterations < $maxIterations) {
                // Check if client disconnected
                if (connection_aborted()) {
                    return;
                }

                // Get current state
                $data = $this->buildDashboardData($tournament);
                $currentState = md5(json_encode($data));

                // Only send if state changed or first iteration
                if ($currentState !== $lastState || $iterations === 0) {
                    $lastState = $currentState;
                    $this->sendSSEEvent('dashboard_update', $data);
                } else {
                    // Send heartbeat to keep connection alive
                    $this->sendSSEEvent('heartbeat', ['timestamp' => time()]);
                }

                // Flush output - this also updates connection_aborted() status
                flush();

                // Check again after flush
                if (connection_aborted()) {
                    return;
                }

                // Wait before next check - use shorter sleeps to detect disconnect faster
                for ($i = 0; $i < 5; $i++) {
                    sleep(1);
                    if (connection_aborted()) {
                        return;
                    }
                }
                $iterations++;
            }

            // Stream ended, send close event
            $this->sendSSEEvent('close', ['reason' => 'timeout']);
        });

        // Set SSE headers
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disable nginx buffering

        return $response;
    }

    /**
     * JSON endpoint for polling fallback.
     *
     * Returns current dashboard state for clients that can't use SSE.
     */
    #[Route('/{id}/state', name: 'api_dashboard_state', methods: ['GET'])]
    public function getState(Tournament $tournament): JsonResponse
    {
        $this->denyAccessUnlessGranted(TournamentVoter::VIEW_DASHBOARD, $tournament);

        $data = $this->buildDashboardData($tournament);

        return $this->json($data);
    }

    /**
     * Send an SSE event.
     */
    private function sendSSEEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
    }

    /**
     * Build the dashboard data payload.
     *
     * @return array{
     *     tournament_id: int,
     *     current_round: int|null,
     *     statistics: array{
     *         total_matches: int,
     *         completed_matches: int,
     *         ongoing_matches: int,
     *         pending_matches: int,
     *         disputed_matches: int,
     *         completion_percentage: float
     *     },
     *     matches: array<int, array{
     *         id: int,
     *         table_number: int,
     *         player1: string,
     *         player2: string|null,
     *         status: string,
     *         player1_score: int|null,
     *         player2_score: int|null,
     *         is_bye: bool
     *     }>,
     *     timer: array{
     *         started_at: string|null,
     *         elapsed_seconds: int
     *     },
     *     timestamp: int
     * }
     */
    private function buildDashboardData(Tournament $tournament): array
    {
        // Use current round if ongoing, otherwise use latest completed round
        $currentRound = $tournament->getCurrentRound();
        $displayRound = $currentRound ?? $tournament->getLatestRound();

        $statistics = [
            'total_matches' => 0,
            'completed_matches' => 0,
            'ongoing_matches' => 0,
            'pending_matches' => 0,
            'disputed_matches' => 0,
            'completion_percentage' => 0.0,
        ];

        $matches = [];
        $timer = [
            'started_at' => null,
            'elapsed_seconds' => 0,
        ];

        if ($displayRound !== null) {
            // Only show timer for ongoing rounds
            if ($currentRound !== null) {
                $timer['started_at'] = $currentRound->getStartedAt()?->format(\DateTimeInterface::ATOM);
                if ($currentRound->getStartedAt() !== null) {
                    $timer['elapsed_seconds'] = time() - $currentRound->getStartedAt()->getTimestamp();
                }
            }

            $roundMatches = $displayRound->getMatches();
            $statistics['total_matches'] = $roundMatches->count();

            $completed = 0;
            $ongoing = 0;
            $pending = 0;
            $disputed = 0;
            $bye = 0;

            foreach ($roundMatches as $match) {
                $matchData = [
                    'id' => $match->getId(),
                    'table_number' => $match->getTableNumber(),
                    'player1' => $match->getPlayer1()->getPlayer()->getPseudo(),
                    'player2' => $match->getPlayer2()?->getPlayer()->getPseudo(),
                    'status' => $match->getStatus()->value,
                    'status_label' => $this->translator->trans($match->getStatus()->getLabel()),
                    'player1_score' => $match->getPlayer1Score(),
                    'player2_score' => $match->getPlayer2Score(),
                    'is_bye' => $match->isByeMatch(),
                ];
                $matches[] = $matchData;

                if ($match->isByeMatch()) {
                    $bye++;
                    $completed++;
                    continue;
                }

                match ($match->getStatus()) {
                    MatchStatus::COMPLETED => $completed++,
                    MatchStatus::ONGOING => $ongoing++,
                    MatchStatus::PENDING => $pending++,
                    MatchStatus::DISPUTE => $disputed++,
                };
            }

            $statistics['completed_matches'] = $completed;
            $statistics['ongoing_matches'] = $ongoing;
            $statistics['pending_matches'] = $pending;
            $statistics['disputed_matches'] = $disputed;

            $playableMatches = $statistics['total_matches'] - $bye;
            $statistics['completion_percentage'] = $playableMatches > 0
                ? round(($completed - $bye) / $playableMatches * 100, 1)
                : 100.0;
        }

        return [
            'tournament_id' => $tournament->getId(),
            'current_round' => $currentRound?->getRoundNumber(),
            'display_round' => $displayRound?->getRoundNumber(),
            'round_completed' => $displayRound?->isCompleted() ?? false,
            'statistics' => $statistics,
            'matches' => $matches,
            'timer' => $timer,
            'timestamp' => time(),
        ];
    }
}
