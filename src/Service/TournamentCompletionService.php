<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for completing tournaments and managing results publication.
 *
 * FR54: System automatically displays final standings at tournament end.
 * FR56: Results published automatically for PUBLIC tournaments.
 * FR57: Organizer can choose to publish for PRIVATE tournaments.
 */
class TournamentCompletionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StandingsService $standingsService,
    ) {
    }

    /**
     * Complete a Swiss-only tournament.
     *
     * This is called when:
     * - All Swiss rounds have been played
     * - No more rounds are scheduled
     * - The organizer confirms completion
     *
     * @return array<int, array{registration: mixed, wins: int, losses: int, matchPoints: int}> Final standings
     */
    public function completeSwissTournament(Tournament $tournament): array
    {
        $this->validateCanComplete($tournament);

        // Calculate final standings
        $standings = $this->standingsService->calculateStandings($tournament);

        // Complete the tournament
        $tournament->setStatus(TournamentStatus::COMPLETED);
        $tournament->setCompletedAt(new \DateTimeImmutable());

        // FR56: Auto-publish results for PUBLIC tournaments
        $this->autoPublishIfPublic($tournament);

        $this->entityManager->flush();

        return $standings;
    }

    /**
     * Check if a tournament can be completed.
     *
     * Requirements:
     * - Tournament must be ONGOING
     * - Current round must be completed (if any)
     */
    public function canComplete(Tournament $tournament): bool
    {
        if (!$tournament->isOngoing()) {
            return false;
        }

        $currentRound = $tournament->getCurrentRound();
        if ($currentRound !== null && !$currentRound->isCompleted()) {
            return false;
        }

        return true;
    }

    /**
     * Check if tournament has reached the expected number of Swiss rounds.
     */
    public function hasCompletedAllSwissRounds(Tournament $tournament): bool
    {
        $expectedRounds = $tournament->getSwissRounds();
        if ($expectedRounds === null) {
            return false;
        }

        $completedRounds = $tournament->getCompletedRoundsCount();

        return $completedRounds >= $expectedRounds;
    }

    /**
     * Get tournament completion status summary.
     *
     * @return array{
     *     canComplete: bool,
     *     isOngoing: bool,
     *     hasCurrentRound: bool,
     *     currentRoundComplete: bool,
     *     expectedRounds: int|null,
     *     completedRounds: int,
     *     message: string
     * }
     */
    public function getCompletionStatus(Tournament $tournament): array
    {
        $currentRound = $tournament->getCurrentRound();
        $canComplete = $this->canComplete($tournament);

        $message = match (true) {
            !$tournament->isOngoing() => 'Le tournoi n\'est pas en cours.',
            $currentRound !== null && !$currentRound->isCompleted() => 'La ronde actuelle n\'est pas terminee.',
            $canComplete => 'Le tournoi peut etre termine.',
            default => 'Statut inconnu.',
        };

        return [
            'canComplete' => $canComplete,
            'isOngoing' => $tournament->isOngoing(),
            'hasCurrentRound' => $currentRound !== null,
            'currentRoundComplete' => $currentRound === null || $currentRound->isCompleted(),
            'expectedRounds' => $tournament->getSwissRounds(),
            'completedRounds' => $tournament->getCompletedRoundsCount(),
            'message' => $message,
        ];
    }

    /**
     * Publish results for a PRIVATE tournament (FR57).
     *
     * Only the organizer can publish results.
     *
     * @throws \LogicException If tournament is not completed
     */
    public function publishResults(Tournament $tournament): void
    {
        if (!$tournament->isCompleted()) {
            throw new \LogicException('Le tournoi doit etre termine pour publier les resultats.');
        }

        $tournament->publishResults();
        $this->entityManager->flush();
    }

    /**
     * Unpublish results for a PRIVATE tournament.
     *
     * Allows organizer to hide results after publication.
     *
     * @throws \LogicException If tournament is not completed
     */
    public function unpublishResults(Tournament $tournament): void
    {
        if (!$tournament->isCompleted()) {
            throw new \LogicException('Le tournoi doit etre termine.');
        }

        $tournament->setResultsPublished(false);
        $this->entityManager->flush();
    }

    /**
     * Auto-publish results for PUBLIC tournaments (FR56).
     */
    private function autoPublishIfPublic(Tournament $tournament): void
    {
        if ($tournament->getVisibility() === TournamentVisibility::PUBLIC) {
            $tournament->publishResults();
        }
    }

    /**
     * Validate tournament can be completed.
     *
     * @throws \LogicException If tournament cannot be completed
     */
    private function validateCanComplete(Tournament $tournament): void
    {
        if (!$this->canComplete($tournament)) {
            $status = $this->getCompletionStatus($tournament);
            throw new \LogicException($status['message']);
        }
    }
}
