<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RegistrationEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $senderEmail = 'noreply@alteredtournamenttools.com',
        private readonly string $senderName = 'Altered Tournament Tools'
    ) {
    }

    public function sendConfirmationEmail(Registration $registration): void
    {
        $tournament = $registration->getTournament();
        $player = $registration->getPlayer();

        $tournamentUrl = $this->urlGenerator->generate(
            'tournament_show',
            ['id' => $tournament->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $unregisterUrl = $this->urlGenerator->generate(
            'tournament_unregister_token',
            ['token' => $registration->getUnregisterToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($player->getEmail(), $player->getPseudo()))
            ->subject(sprintf('Inscription confirmee - %s', $tournament->getName()))
            ->htmlTemplate('emails/registration_confirmation.html.twig')
            ->textTemplate('emails/registration_confirmation.txt.twig')
            ->context([
                'tournament' => $tournament,
                'player' => $player,
                'registration' => $registration,
                'tournamentUrl' => $tournamentUrl,
                'unregisterUrl' => $unregisterUrl,
            ]);

        try {
            $this->mailer->send($email);

            $this->logger->info('Registration confirmation email sent', [
                'player_id' => $player->getId(),
                'player_email' => $player->getEmail(),
                'tournament_id' => $tournament->getId(),
                'tournament_name' => $tournament->getName(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send registration confirmation email', [
                'player_id' => $player->getId(),
                'player_email' => $player->getEmail(),
                'tournament_id' => $tournament->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function sendRemovalEmail(
        string $playerEmail,
        string $playerPseudo,
        string $tournamentName,
        int $tournamentId,
        ?string $reason = null
    ): void {
        $tournamentUrl = $this->urlGenerator->generate(
            'tournament_show',
            ['id' => $tournamentId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($playerEmail, $playerPseudo))
            ->subject(sprintf('Inscription retiree - %s', $tournamentName))
            ->htmlTemplate('emails/registration_removal.html.twig')
            ->textTemplate('emails/registration_removal.txt.twig')
            ->context([
                'playerPseudo' => $playerPseudo,
                'tournamentName' => $tournamentName,
                'tournamentUrl' => $tournamentUrl,
                'reason' => $reason,
            ]);

        try {
            $this->mailer->send($email);

            $this->logger->info('Registration removal email sent', [
                'player_email' => $playerEmail,
                'tournament_id' => $tournamentId,
                'tournament_name' => $tournamentName,
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send registration removal email', [
                'player_email' => $playerEmail,
                'tournament_id' => $tournamentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
