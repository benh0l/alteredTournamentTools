<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendAdminEmailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * Handler for sending admin emails (FR71).
 */
#[AsMessageHandler]
final class SendAdminEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $senderEmail = 'noreply@alteredtournamenttools.fr',
    ) {
    }

    public function __invoke(SendAdminEmailMessage $message): void
    {
        try {
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($message->getRecipientEmail())
                ->subject('[AlteredTournamentTools] ' . $message->getSubject())
                ->text($this->renderTextContent($message))
                ->html($this->renderHtmlContent($message));

            $this->mailer->send($email);

            $this->logger->info('Admin email sent', [
                'recipient' => $message->getRecipientEmail(),
                'subject' => $message->getSubject(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin email', [
                'recipient' => $message->getRecipientEmail(),
                'subject' => $message->getSubject(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function renderTextContent(SendAdminEmailMessage $message): string
    {
        return <<<TEXT
Bonjour {$message->getRecipientName()},

{$message->getBody()}

---
Message de l'administration AlteredTournamentTools
Envoye par: {$message->getSenderName()}
TEXT;
    }

    private function renderHtmlContent(SendAdminEmailMessage $message): string
    {
        $body = nl2br(htmlspecialchars($message->getBody()));

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{$message->getSubject()}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #1a56db 0%, #7e3af2 100%); padding: 20px; border-radius: 8px 8px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Altered Tournament Tools</h1>
    </div>

    <div style="background: white; padding: 30px; border: 1px solid #e5e7eb; border-top: none;">
        <p style="margin-top: 0;">Bonjour <strong>{$message->getRecipientName()}</strong>,</p>

        <div style="margin: 20px 0; padding: 15px; background: #f9fafb; border-radius: 4px;">
            {$body}
        </div>
    </div>

    <div style="background: #f3f4f6; padding: 15px; border-radius: 0 0 8px 8px; text-align: center; font-size: 12px; color: #6b7280;">
        <p style="margin: 0;">Message de l'administration AlteredTournamentTools</p>
        <p style="margin: 5px 0 0;">Envoye par: {$message->getSenderName()}</p>
    </div>
</body>
</html>
HTML;
    }
}
