<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ApiAccessRequest;
use App\Entity\ApiCredential;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service for sending application emails.
 */
final class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserRepository $userRepository,
        private readonly string $fromAddress,
    ) {
    }

    /**
     * Send a test email to verify mailer configuration.
     */
    public function sendTestEmail(string $toEmail, string $recipientName): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($toEmail)
            ->subject('Email de test - Altered Tournament Tools')
            ->htmlTemplate('emails/test.html.twig')
            ->textTemplate('emails/test.txt.twig')
            ->context([
                'recipientName' => $recipientName,
                'testUrl' => 'https://alteredtournamenttools.com',
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send registration confirmation email.
     */
    public function sendRegistrationConfirmation(
        string $toEmail,
        string $playerName,
        string $tournamentName,
        \DateTimeInterface $tournamentDate,
        string $location,
    ): void {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($toEmail)
            ->subject(sprintf('Inscription confirmee - %s', $tournamentName))
            ->htmlTemplate('emails/registration_confirmation.html.twig')
            ->textTemplate('emails/registration_confirmation.txt.twig')
            ->context([
                'playerName' => $playerName,
                'tournamentName' => $tournamentName,
                'tournamentDate' => $tournamentDate,
                'location' => $location,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send password reset email.
     */
    public function sendPasswordReset(User $user, string $token): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($user->getEmail())
            ->subject('Reinitialisation de mot de passe - Altered Tournament Tools')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expirationMinutes' => 60,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send password changed confirmation email.
     */
    public function sendPasswordChanged(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($user->getEmail())
            ->subject('Mot de passe modifie - Altered Tournament Tools')
            ->htmlTemplate('emails/password_changed.html.twig')
            ->textTemplate('emails/password_changed.txt.twig')
            ->context([
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send welcome email after user registration.
     */
    public function sendWelcomeEmail(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($user->getEmail())
            ->subject('Bienvenue sur Altered Tournament Tools !')
            ->htmlTemplate('emails/welcome.html.twig')
            ->textTemplate('emails/welcome.txt.twig')
            ->context([
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send notification when a player is dropped from a tournament by the organizer.
     */
    public function sendPlayerDroppedNotification(
        string $toEmail,
        string $playerName,
        string $tournamentName,
        string $reason = ''
    ): void {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($toEmail)
            ->subject(sprintf('Retrait du tournoi - %s', $tournamentName))
            ->htmlTemplate('emails/player_dropped.html.twig')
            ->textTemplate('emails/player_dropped.txt.twig')
            ->context([
                'playerName' => $playerName,
                'tournamentName' => $tournamentName,
                'reason' => $reason,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send notification to admins when a new API access request is submitted.
     */
    public function sendApiRequestSubmittedToAdmin(ApiAccessRequest $request): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');

        $adminUrl = $this->urlGenerator->generate(
            'admin_api_access_view',
            ['id' => $request->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        foreach ($admins as $admin) {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
                ->to($admin->getEmail())
                ->subject('Nouvelle demande d\'acces API - ' . $request->getProjectName())
                ->htmlTemplate('emails/api_request_submitted.html.twig')
                ->textTemplate('emails/api_request_submitted.txt.twig')
                ->context([
                    'request' => $request,
                    'adminUrl' => $adminUrl,
                ]);

            $this->mailer->send($email);
        }
    }

    /**
     * Send notification to user when their API access request is approved.
     */
    public function sendApiRequestApproved(ApiAccessRequest $request, ApiCredential $credential): void
    {
        $user = $request->getUser();

        $apiUrl = $this->urlGenerator->generate(
            'user_api_access',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $docUrl = $this->urlGenerator->generate(
            'app.swagger_ui',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($user->getEmail())
            ->subject('Votre demande d\'acces API a ete approuvee !')
            ->htmlTemplate('emails/api_request_approved.html.twig')
            ->textTemplate('emails/api_request_approved.txt.twig')
            ->context([
                'user' => $user,
                'request' => $request,
                'credential' => $credential,
                'apiUrl' => $apiUrl,
                'docUrl' => $docUrl,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send notification to user when their API access request is rejected.
     */
    public function sendApiRequestRejected(ApiAccessRequest $request): void
    {
        $user = $request->getUser();

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Altered Tournament Tools'))
            ->to($user->getEmail())
            ->subject('Votre demande d\'acces API n\'a pas ete approuvee')
            ->htmlTemplate('emails/api_request_rejected.html.twig')
            ->textTemplate('emails/api_request_rejected.txt.twig')
            ->context([
                'user' => $user,
                'request' => $request,
            ]);

        $this->mailer->send($email);
    }
}
