<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Repository\ContactReportRepository;
use App\Repository\RegistrationRepository;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\TournamentMatchRepository;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AccountDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResetPasswordRequestRepository $resetPasswordRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly TournamentRepository $tournamentRepository,
        private readonly TournamentMatchRepository $matchRepository,
        private readonly ContactReportRepository $contactReportRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Delete a user account and all associated data.
     * RGPD-compliant deletion with transaction safety.
     */
    public function deleteAccount(User $user): void
    {
        $userId = $user->getId();

        $this->entityManager->beginTransaction();

        try {
            // 1. Delete password reset requests
            $this->resetPasswordRepository->removeAllForUser($user);

            // 2. TODO: Delete tournament registrations when Registration entity exists
            // $this->registrationRepository->removeAllForUser($user);

            // 3. TODO: Anonymize match results when Match entity exists
            // $this->anonymizeMatchResults($user);

            // 4. TODO: Handle tournaments created by user when Tournament entity exists
            // $this->handleUserTournaments($user);

            // 5. Delete user entity
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->entityManager->commit();

            // Log for audit (no personal data)
            $this->logger->info('User account deleted', [
                'user_id' => $userId,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->logger->error('Account deletion failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Export all user data in RGPD-compliant format.
     *
     * @return array<string, mixed>
     */
    public function exportUserData(User $user): array
    {
        return [
            'profile' => [
                'email' => $user->getEmail(),
                'pseudo' => $user->getPseudo(),
                'name' => $user->getName(),
                'team_tag' => $user->getTeamTag(),
                'roles' => $user->getRoles(),
                'created_at' => $user->getCreatedAt()->format('c'),
                'updated_at' => $user->getUpdatedAt()?->format('c'),
                'is_verified' => $user->isVerified(),
                'theme_preferences' => [
                    'color' => $user->getThemeColor(),
                    'mode' => $user->getThemeMode(),
                ],
                'notification_preferences' => $user->getNotificationPreferences(),
            ],
            'registrations' => $this->getRegistrationsData($user),
            'tournaments_organized' => $this->getTournamentsData($user),
            'match_history' => $this->getMatchHistoryData($user),
            'contact_reports' => $this->getContactReportsData($user),
            'exported_at' => (new \DateTimeImmutable())->format('c'),
        ];
    }

    /**
     * Get all tournament registrations for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRegistrationsData(User $user): array
    {
        $registrations = $this->registrationRepository->findByPlayerWithTournament($user);
        $data = [];

        foreach ($registrations as $registration) {
            $tournament = $registration->getTournament();
            $data[] = [
                'tournament' => [
                    'id' => $tournament->getId(),
                    'name' => $tournament->getName(),
                    'date' => $tournament->getDate()->format('c'),
                    'location' => $tournament->getLocation(),
                    'status' => $tournament->getStatus()->value,
                ],
                'registered_at' => $registration->getRegisteredAt()->format('c'),
                'faction' => $registration->getFaction()?->value,
                'faction2' => $registration->getFaction2()?->value,
                'hero' => $registration->getHero(),
                'decklist_url' => $registration->getDecklistUrl(),
            ];
        }

        return $data;
    }

    /**
     * Get all tournaments organized by a user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTournamentsData(User $user): array
    {
        $tournaments = $this->tournamentRepository->findByOrganizer($user);
        $data = [];

        foreach ($tournaments as $tournament) {
            $data[] = [
                'id' => $tournament->getId(),
                'name' => $tournament->getName(),
                'date' => $tournament->getDate()->format('c'),
                'location' => $tournament->getLocation(),
                'status' => $tournament->getStatus()->value,
                'format' => $tournament->getFormat()->value,
                'max_players' => $tournament->getMaxPlayers(),
                'player_count' => $tournament->getRegistrations()->count(),
                'created_at' => $tournament->getCreatedAt()->format('c'),
            ];
        }

        return $data;
    }

    /**
     * Get match history for a user across all tournaments.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getMatchHistoryData(User $user): array
    {
        $registrations = $this->registrationRepository->findByPlayer($user);
        $data = [];

        foreach ($registrations as $registration) {
            $matches = $this->matchRepository->findByPlayer($registration);
            $tournament = $registration->getTournament();

            foreach ($matches as $match) {
                $data[] = $this->formatMatchData($match, $registration, $tournament->getName());
            }
        }

        return $data;
    }

    /**
     * Get all contact reports submitted by a user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getContactReportsData(User $user): array
    {
        $reports = $this->contactReportRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $data = [];

        foreach ($reports as $report) {
            $data[] = [
                'subject' => $report->getSubject()->value,
                'message' => $report->getMessage(),
                'page_url' => $report->getPageUrl(),
                'status' => $report->getStatus()->value,
                'created_at' => $report->getCreatedAt()->format('c'),
            ];
        }

        return $data;
    }

    /**
     * Format a single match for export.
     *
     * @return array<string, mixed>
     */
    private function formatMatchData(TournamentMatch $match, Registration $userRegistration, string $tournamentName): array
    {
        $opponent = $match->getOpponent($userRegistration);
        $isPlayer1 = $match->getPlayer1() === $userRegistration;
        $result = $match->getResult();

        $userScore = $isPlayer1 ? ($result['player1Score'] ?? null) : ($result['player2Score'] ?? null);
        $opponentScore = $isPlayer1 ? ($result['player2Score'] ?? null) : ($result['player1Score'] ?? null);

        $outcome = null;
        if ($match->isCompleted() && $result !== null) {
            $winner = $match->getWinner();
            if ($match->isByeMatch()) {
                $outcome = 'bye';
            } elseif ($winner === $userRegistration) {
                $outcome = 'win';
            } elseif ($winner !== null) {
                $outcome = 'loss';
            }
        }

        return [
            'tournament' => $tournamentName,
            'round' => $match->getRound()->getRoundNumber(),
            'table' => $match->getTableNumber(),
            'opponent' => $opponent?->getPlayer()->getPseudo(),
            'is_bye' => $match->isByeMatch(),
            'status' => $match->getStatus()->value,
            'outcome' => $outcome,
            'user_score' => $userScore,
            'opponent_score' => $opponentScore,
            'played_at' => $match->getCompletedAt()?->format('c'),
        ];
    }
}
