<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendRegistrationRemovalMessage;
use App\Service\RegistrationEmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendRegistrationRemovalHandler
{
    public function __construct(
        private RegistrationEmailService $emailService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SendRegistrationRemovalMessage $message): void
    {
        try {
            $this->emailService->sendRemovalEmail(
                $message->getPlayerEmail(),
                $message->getPlayerPseudo(),
                $message->getTournamentName(),
                $message->getTournamentId(),
                $message->getReason()
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send registration removal email', [
                'player_email' => $message->getPlayerEmail(),
                'tournament_id' => $message->getTournamentId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
