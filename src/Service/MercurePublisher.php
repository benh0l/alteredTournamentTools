<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Service for publishing real-time updates via Mercure SSE.
 *
 * Topic format: /{domain}/{entity}/{id}
 * Example: /tournament/123 for tournament-specific updates
 */
final class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Publish tournament update.
     *
     * @param int $tournamentId Tournament identifier
     * @param string $eventType Event type (e.g., 'match.completed', 'round.started')
     * @param array<string, mixed> $data Event payload
     */
    public function publishTournamentUpdate(
        int $tournamentId,
        string $eventType,
        array $data
    ): void {
        $topic = sprintf('/tournament/%d', $tournamentId);

        $payload = json_encode([
            'type' => $eventType,
            'tournamentId' => $tournamentId,
            'data' => $data,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        $update = new Update(
            topics: $topic,
            data: $payload,
            private: false,
            type: $eventType
        );

        try {
            $this->hub->publish($update);
            $this->logger->info('Mercure update published', [
                'topic' => $topic,
                'type' => $eventType,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to publish Mercure update', [
                'topic' => $topic,
                'type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - SSE failure should not break main flow
        }
    }

    /**
     * Publish match result update.
     *
     * @param array<string, mixed> $result Match result data
     */
    public function publishMatchUpdate(int $tournamentId, int $matchId, array $result): void
    {
        $this->publishTournamentUpdate($tournamentId, 'match.completed', [
            'matchId' => $matchId,
            'result' => $result,
        ]);
    }

    /**
     * Publish round started event.
     */
    public function publishRoundStarted(int $tournamentId, int $roundNumber): void
    {
        $this->publishTournamentUpdate($tournamentId, 'round.started', [
            'roundNumber' => $roundNumber,
        ]);
    }

    /**
     * Publish standings update.
     *
     * @param array<int, array<string, mixed>> $standings Standings data
     */
    public function publishStandingsUpdate(int $tournamentId, array $standings): void
    {
        $this->publishTournamentUpdate($tournamentId, 'standings.updated', [
            'standings' => $standings,
        ]);
    }

    /**
     * Publish timer update for dashboard.
     */
    public function publishTimerUpdate(int $tournamentId, int $remainingSeconds): void
    {
        $this->publishTournamentUpdate($tournamentId, 'timer.updated', [
            'remainingSeconds' => $remainingSeconds,
        ]);
    }
}
