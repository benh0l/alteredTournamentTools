<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendRegistrationConfirmationMessage;
use App\Repository\RegistrationRepository;
use App\Service\RegistrationEmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendRegistrationConfirmationHandler
{
    public function __construct(
        private RegistrationRepository $registrationRepository,
        private RegistrationEmailService $emailService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SendRegistrationConfirmationMessage $message): void
    {
        $registration = $this->registrationRepository->find($message->getRegistrationId());

        if ($registration === null) {
            $this->logger->warning('Registration not found for email confirmation', [
                'registration_id' => $message->getRegistrationId(),
            ]);

            return;
        }

        try {
            $this->emailService->sendConfirmationEmail($registration);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send registration confirmation email', [
                'registration_id' => $message->getRegistrationId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
