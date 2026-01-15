<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\RoundStartedEvent;
use App\Service\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber that sends round start notifications when a round begins (FR66).
 *
 * Listens to RoundStartedEvent and queues notifications
 * for all players in that round's matches.
 */
final class RoundNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RoundStartedEvent::class => 'onRoundStarted',
        ];
    }

    public function onRoundStarted(RoundStartedEvent $event): void
    {
        $round = $event->getRound();
        $tournament = $round->getTournament();

        $this->logger->info('Round started, queuing notifications', [
            'tournament_id' => $tournament->getId(),
            'round_number' => $round->getRoundNumber(),
        ]);

        try {
            $stats = $this->notificationService->queueRoundStartNotifications(
                $tournament,
                $round->getRoundNumber()
            );

            $this->logger->info('Round start notifications queued', [
                'tournament_id' => $tournament->getId(),
                'round_number' => $round->getRoundNumber(),
                'queued' => $stats['queued'],
                'skipped' => $stats['skipped'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to queue round start notifications', [
                'tournament_id' => $tournament->getId(),
                'round_number' => $round->getRoundNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
