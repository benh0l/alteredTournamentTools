<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\PlayerDroppedEvent;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber that sends notification when a player is dropped from a tournament.
 *
 * Only sends notification when the drop is initiated by the organizer.
 * Self-drops do not trigger notifications (the player already knows).
 */
final class PlayerDroppedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PlayerDroppedEvent::class => 'onPlayerDropped',
        ];
    }

    public function onPlayerDropped(PlayerDroppedEvent $event): void
    {
        // Only send notification if dropped by organizer
        if (!$event->isByOrganizer()) {
            return;
        }

        $registration = $event->getRegistration();
        $player = $registration->getPlayer();
        $tournament = $registration->getTournament();

        $this->logger->info('Player dropped by organizer, sending notification', [
            'player_id' => $player->getId(),
            'tournament_id' => $tournament->getId(),
            'reason' => $event->getReason(),
        ]);

        try {
            $this->emailService->sendPlayerDroppedNotification(
                $player->getEmail(),
                $player->getDisplayName(),
                $tournament->getName(),
                $event->getReason()
            );

            $this->logger->info('Player drop notification sent', [
                'player_id' => $player->getId(),
                'tournament_id' => $tournament->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send player drop notification', [
                'player_id' => $player->getId(),
                'tournament_id' => $tournament->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
