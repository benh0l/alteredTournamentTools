<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentStatus;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class TournamentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TournamentRepository $tournamentRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create a new tournament with the given organizer.
     * Tournament is created in DRAFT status.
     * Auto-grants ROLE_ORGANIZER to the user if not already assigned.
     */
    public function createTournament(Tournament $tournament, User $organizer): Tournament
    {
        $tournament->setOrganizer($organizer);
        $tournament->setStatus(TournamentStatus::DRAFT);

        // Auto-grant ROLE_ORGANIZER on first tournament creation
        // Skip if user already has ROLE_ADMIN (which includes ROLE_ORGANIZER via hierarchy)
        if (!$organizer->hasRole('ROLE_ORGANIZER') && !$organizer->hasRole('ROLE_ADMIN')) {
            $organizer->addRole('ROLE_ORGANIZER');
            $this->logger->info('Auto-granted ROLE_ORGANIZER to user', [
                'user_id' => $organizer->getId(),
            ]);
        }

        $this->entityManager->persist($tournament);
        $this->entityManager->flush();

        $this->logger->info('Tournament created', [
            'tournament_id' => $tournament->getId(),
            'tournament_name' => $tournament->getName(),
            'organizer_id' => $organizer->getId(),
        ]);

        return $tournament;
    }

    /**
     * Update an existing tournament.
     * Only allowed if tournament is in DRAFT status.
     */
    public function updateTournament(Tournament $tournament): Tournament
    {
        if (!$tournament->isEditable()) {
            throw new \LogicException('Cannot edit tournament that is not in DRAFT status');
        }

        $this->entityManager->flush();

        $this->logger->info('Tournament updated', [
            'tournament_id' => $tournament->getId(),
            'tournament_name' => $tournament->getName(),
        ]);

        return $tournament;
    }

    /**
     * Publish a tournament (make it visible and open for registration).
     */
    public function publishTournament(Tournament $tournament): Tournament
    {
        if ($tournament->getStatus() !== TournamentStatus::DRAFT) {
            throw new \LogicException('Only DRAFT tournaments can be published');
        }

        $tournament->setStatus(TournamentStatus::PUBLISHED);
        $tournament->setPublishedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->info('Tournament published', [
            'tournament_id' => $tournament->getId(),
            'tournament_name' => $tournament->getName(),
        ]);

        return $tournament;
    }

    /**
     * Delete a tournament.
     * Only allowed if tournament is in DRAFT or PUBLISHED status (not started yet).
     */
    public function deleteTournament(Tournament $tournament): void
    {
        if (!$tournament->getStatus()->isDeletable()) {
            throw new \LogicException('Cannot delete tournament that has already started');
        }

        $tournamentId = $tournament->getId();
        $tournamentName = $tournament->getName();
        $registrationCount = $tournament->getRegistrations()->count();

        // Remove all registrations first (for published tournaments)
        foreach ($tournament->getRegistrations() as $registration) {
            $this->entityManager->remove($registration);
        }

        $this->entityManager->remove($tournament);
        $this->entityManager->flush();

        $this->logger->info('Tournament deleted', [
            'tournament_id' => $tournamentId,
            'tournament_name' => $tournamentName,
            'registrations_removed' => $registrationCount,
        ]);
    }

    /**
     * Get tournaments organized by a specific user.
     *
     * @return Tournament[]
     */
    public function getTournamentsByOrganizer(User $organizer): array
    {
        return $this->tournamentRepository->findByOrganizer($organizer);
    }

    /**
     * Get all public upcoming tournaments.
     *
     * @return Tournament[]
     */
    public function getUpcomingPublicTournaments(): array
    {
        return $this->tournamentRepository->findUpcomingPublicTournaments();
    }

    /**
     * Calculate suggested number of Swiss rounds based on expected players and match format.
     * Uses the Altered TCG rule: BO1 adds +1 round compared to standard Swiss.
     */
    public function suggestSwissRounds(int $expectedPlayers, bool $isBo1 = false): int
    {
        if ($expectedPlayers < 4) {
            return 1;
        }

        // Standard Swiss: ceil(log2(players))
        $baseRounds = (int) ceil(log($expectedPlayers, 2));

        // Altered TCG BO1 rule: +1 round
        if ($isBo1) {
            $baseRounds++;
        }

        // Cap at reasonable maximum
        return min($baseRounds, 10);
    }
}
