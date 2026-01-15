<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use App\Enum\TournamentStatus;
use App\Message\SendTournamentReminderMessage;
use App\Repository\NotificationRepository;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service for managing tournament notifications (FR64-FR67).
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly TournamentRepository $tournamentRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Queue D-1 (day before) notifications for all tournaments starting tomorrow.
     *
     * @return array{queued: int, skipped: int}
     */
    public function queueDayBeforeNotifications(): array
    {
        $tomorrow = (new \DateTimeImmutable())->modify('+1 day')->setTime(0, 0, 0);
        $dayAfterTomorrow = $tomorrow->modify('+1 day');

        $tournaments = $this->tournamentRepository->createQueryBuilder('t')
            ->where('t.date >= :tomorrow')
            ->andWhere('t.date < :dayAfterTomorrow')
            ->andWhere('t.status = :status')
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('dayAfterTomorrow', $dayAfterTomorrow)
            ->setParameter('status', TournamentStatus::PUBLISHED)
            ->getQuery()
            ->getResult();

        $stats = ['queued' => 0, 'skipped' => 0];

        foreach ($tournaments as $tournament) {
            $result = $this->queueNotificationsForTournament(
                $tournament,
                NotificationType::D_MINUS_1
            );
            $stats['queued'] += $result['queued'];
            $stats['skipped'] += $result['skipped'];
        }

        $this->logger->info('Day before notifications queued', [
            'tournaments_found' => count($tournaments),
            'queued' => $stats['queued'],
            'skipped' => $stats['skipped'],
        ]);

        return $stats;
    }

    /**
     * Queue H-1 (hour before) notifications for tournaments starting in about 1 hour.
     *
     * @return array{queued: int, skipped: int}
     */
    public function queueHourBeforeNotifications(): array
    {
        $now = new \DateTimeImmutable();
        $inOneHour = $now->modify('+1 hour');
        $inTwoHours = $now->modify('+2 hours');

        // Find tournaments that start in the next hour
        $tournaments = $this->tournamentRepository->createQueryBuilder('t')
            ->where('t.date = :today')
            ->andWhere('t.time IS NOT NULL')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('today', $now->setTime(0, 0, 0))
            ->setParameter('statuses', [TournamentStatus::PUBLISHED, TournamentStatus::ONGOING])
            ->getQuery()
            ->getResult();

        $stats = ['queued' => 0, 'skipped' => 0];

        foreach ($tournaments as $tournament) {
            $time = $tournament->getTime();
            if ($time === null) {
                continue;
            }

            $tournamentDateTime = $tournament->getDate()->setTime(
                (int) $time->format('H'),
                (int) $time->format('i')
            );

            // Check if tournament starts within the next 60-90 minutes
            if ($tournamentDateTime >= $inOneHour && $tournamentDateTime < $inTwoHours) {
                $result = $this->queueNotificationsForTournament(
                    $tournament,
                    NotificationType::H_MINUS_1
                );
                $stats['queued'] += $result['queued'];
                $stats['skipped'] += $result['skipped'];
            }
        }

        $this->logger->info('Hour before notifications queued', $stats);

        return $stats;
    }

    /**
     * Queue round start notifications for a specific tournament round.
     *
     * @return array{queued: int, skipped: int}
     */
    public function queueRoundStartNotifications(Tournament $tournament, int $roundNumber): array
    {
        return $this->queueNotificationsForTournament(
            $tournament,
            NotificationType::ROUND_START,
            $roundNumber
        );
    }

    /**
     * Queue notifications for all registered players of a tournament.
     *
     * @return array{queued: int, skipped: int}
     */
    private function queueNotificationsForTournament(
        Tournament $tournament,
        NotificationType $type,
        ?int $roundNumber = null
    ): array {
        $registrations = $this->registrationRepository->findBy(['tournament' => $tournament]);
        $stats = ['queued' => 0, 'skipped' => 0, 'disabled' => 0];

        // Map notification type to preference key
        $preferenceKey = $this->getPreferenceKeyForType($type);

        foreach ($registrations as $registration) {
            $player = $registration->getPlayer();

            // Check if notification was already sent
            if ($this->notificationRepository->hasBeenSent($player, $tournament, $type)) {
                $stats['skipped']++;
                continue;
            }

            // Check user preferences (FR67)
            if (!$this->isNotificationEnabledForUser($player, $preferenceKey)) {
                $this->logger->debug('Notification skipped due to user preferences', [
                    'user_id' => $player->getId(),
                    'type' => $type->value,
                ]);
                $stats['disabled']++;
                continue;
            }

            // Create and dispatch notification
            $notification = $this->createNotification($player, $tournament, $type, $roundNumber);
            $this->dispatchNotification($notification, $roundNumber);
            $stats['queued']++;
        }

        return $stats;
    }

    /**
     * Check if a notification type is enabled for a user.
     */
    private function isNotificationEnabledForUser(User $user, string $preferenceKey): bool
    {
        // Check if type is enabled
        if (!$user->isNotificationEnabled($preferenceKey)) {
            return false;
        }

        // Check if email channel is enabled
        if (!$user->isEmailNotificationEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * Get the preference key for a notification type.
     */
    private function getPreferenceKeyForType(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::D_MINUS_1 => 'd_minus_1',
            NotificationType::H_MINUS_1 => 'h_minus_1',
            NotificationType::ROUND_START => 'round_start',
        };
    }

    /**
     * Create a notification entity.
     */
    public function createNotification(
        User $user,
        Tournament $tournament,
        NotificationType $type,
        ?int $roundNumber = null
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTournament($tournament);
        $notification->setType($type);
        $notification->setRoundNumber($roundNumber);

        $this->notificationRepository->save($notification, true);

        return $notification;
    }

    /**
     * Dispatch a notification to the message bus.
     */
    public function dispatchNotification(Notification $notification, ?int $roundNumber = null): void
    {
        $message = new SendTournamentReminderMessage(
            $notification->getId(),
            $notification->getUser()->getId(),
            $notification->getTournament()->getId(),
            $notification->getType(),
            $roundNumber
        );

        $this->messageBus->dispatch($message);
    }

    /**
     * Get notification statistics for a tournament.
     *
     * @return array<string, array<string, int>>
     */
    public function getStats(Tournament $tournament): array
    {
        $notifications = $this->notificationRepository->findByTournament($tournament);

        $stats = [];
        foreach (NotificationType::cases() as $type) {
            $stats[$type->value] = [
                NotificationStatus::PENDING->value => 0,
                NotificationStatus::SENT->value => 0,
                NotificationStatus::FAILED->value => 0,
            ];
        }

        foreach ($notifications as $notification) {
            $stats[$notification->getType()->value][$notification->getStatus()->value]++;
        }

        return $stats;
    }
}
