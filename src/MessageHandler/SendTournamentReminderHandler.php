<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Message\SendTournamentReminderMessage;
use App\Repository\NotificationRepository;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use App\Service\CalendarService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handler for sending tournament reminder emails asynchronously.
 */
#[AsMessageHandler]
final class SendTournamentReminderHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly NotificationRepository $notificationRepository,
        private readonly UserRepository $userRepository,
        private readonly TournamentRepository $tournamentRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly CalendarService $calendarService,
        private readonly LoggerInterface $logger,
        private readonly string $fromAddress,
    ) {
    }

    public function __invoke(SendTournamentReminderMessage $message): void
    {
        $notification = $this->notificationRepository->find($message->getNotificationId());
        if ($notification === null) {
            $this->logger->warning('Notification not found', [
                'notification_id' => $message->getNotificationId(),
            ]);

            return;
        }

        $user = $this->userRepository->find($message->getUserId());
        $tournament = $this->tournamentRepository->find($message->getTournamentId());

        if ($user === null || $tournament === null) {
            $notification->markAsFailed('User or tournament not found');
            $this->notificationRepository->save($notification, true);
            $this->logger->warning('User or tournament not found for notification', [
                'user_id' => $message->getUserId(),
                'tournament_id' => $message->getTournamentId(),
            ]);

            return;
        }

        try {
            $this->sendEmail($notification, $message->getType(), $message->getRoundNumber());
            $notification->markAsSent();
            $this->notificationRepository->save($notification, true);

            $this->logger->info('Tournament reminder sent', [
                'user_id' => $user->getId(),
                'tournament_id' => $tournament->getId(),
                'type' => $message->getType()->value,
            ]);
        } catch (\Throwable $e) {
            $notification->markAsFailed($e->getMessage());
            $this->notificationRepository->save($notification, true);

            $this->logger->error('Failed to send tournament reminder', [
                'user_id' => $user->getId(),
                'tournament_id' => $tournament->getId(),
                'type' => $message->getType()->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendEmail(Notification $notification, NotificationType $type, ?int $roundNumber): void
    {
        $user = $notification->getUser();
        $tournament = $notification->getTournament();

        $subject = $type->getEmailSubject($tournament->getName());
        $template = $this->getTemplateForType($type);

        $tournamentUrl = $this->urlGenerator->generate(
            'tournament_public_details',
            ['id' => $tournament->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        // Get unregister URL using the registration's token
        $unregisterUrl = null;
        $registration = $this->registrationRepository->findOneBy([
            'player' => $user,
            'tournament' => $tournament,
        ]);

        if ($registration !== null) {
            $unregisterUrl = $this->urlGenerator->generate(
                'tournament_unregister_token',
                ['token' => $registration->getUnregisterToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        $calendarUrl = $this->urlGenerator->generate(
            'tournament_calendar_ics',
            ['id' => $tournament->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $context = [
            'user' => $user,
            'tournament' => $tournament,
            'tournamentUrl' => $tournamentUrl,
            'unregisterUrl' => $unregisterUrl,
            'calendarUrl' => $calendarUrl,
            'roundNumber' => $roundNumber,
        ];

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($user->getEmail())
            ->subject($subject)
            ->htmlTemplate($template . '.html.twig')
            ->textTemplate($template . '.txt.twig')
            ->context($context);

        $this->mailer->send($email);
    }

    private function getTemplateForType(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::D_MINUS_1 => 'emails/tournament_d_minus_1',
            NotificationType::H_MINUS_1 => 'emails/tournament_h_minus_1',
            NotificationType::ROUND_START => 'emails/tournament_round_start',
        };
    }
}
