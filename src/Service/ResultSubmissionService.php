<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MatchSubmission;
use App\Entity\Registration;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Exception\InvalidSubmissionException;
use App\Repository\MatchSubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for handling match result submissions.
 *
 * This service implements the autonomous result submission system:
 * 1. Each player submits their view of the result independently
 * 2. When both submit matching results, the match is auto-validated
 * 3. When submissions conflict, a dispute is created for organizer resolution
 */
class ResultSubmissionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MatchSubmissionRepository $submissionRepository,
    ) {
    }

    /**
     * Submit a match result from a player.
     *
     * @param TournamentMatch $match        The match
     * @param User            $submitter    The user submitting the result
     * @param int|null        $claimedWinnerId Registration ID of claimed winner (null for tie)
     * @param int             $player1Score Player 1's score
     * @param int             $player2Score Player 2's score
     * @param array<int, array{winner: string}>|null $gameDetails Game-by-game details for BO3
     *
     * @throws InvalidSubmissionException If submission is invalid
     *
     * @return MatchSubmission The created submission
     */
    public function submitResult(
        TournamentMatch $match,
        User $submitter,
        ?int $claimedWinnerId,
        int $player1Score,
        int $player2Score,
        ?array $gameDetails = null
    ): MatchSubmission {
        // Validate submission
        $this->validateSubmission($match, $submitter);

        // Create submission
        $submission = new MatchSubmission();
        $submission->setMatch($match);
        $submission->setSubmittedBy($submitter);
        $submission->setClaimedWinnerId($claimedWinnerId);
        $submission->setPlayer1Score($player1Score);
        $submission->setPlayer2Score($player2Score);
        $submission->setGameDetails($gameDetails);

        // Add to match and log in history
        $match->addSubmission($submission);
        $match->addDisputeHistoryEntry('submission', [
            'submittedBy' => $submitter->getId(),
            'claimedWinner' => $claimedWinnerId,
            'score' => [$player1Score, $player2Score],
        ]);

        // Update match status to ongoing if it was pending
        if ($match->isPending()) {
            $match->setStatus(MatchStatus::ONGOING);
        }

        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        // Check if we can validate or need to dispute
        $this->processSubmissions($match);

        return $submission;
    }

    /**
     * Validate that a submission can be made.
     *
     * @throws InvalidSubmissionException
     */
    private function validateSubmission(TournamentMatch $match, User $submitter): void
    {
        // Match must not be completed
        if ($match->isCompleted()) {
            throw InvalidSubmissionException::matchAlreadyCompleted();
        }

        // Match must not be a bye
        if ($match->isByeMatch()) {
            throw InvalidSubmissionException::cannotSubmitForBye();
        }

        // User must be a participant
        $registration = $this->findPlayerRegistration($match, $submitter);
        if ($registration === null) {
            throw InvalidSubmissionException::notAParticipant();
        }

        // User must not have already submitted
        if ($this->submissionRepository->hasUserSubmitted($match, $submitter)) {
            throw InvalidSubmissionException::alreadySubmitted();
        }
    }

    /**
     * Process submissions after a new one is added.
     *
     * If both players have submitted:
     * - If they agree, validate the match
     * - If they disagree, create a dispute
     */
    private function processSubmissions(TournamentMatch $match): void
    {
        if (!$match->hasBothSubmissions()) {
            return;
        }

        $submissions = $match->getSubmissions()->toArray();
        $submission1 = $submissions[0];
        $submission2 = $submissions[1];

        if ($submission1->agreesWith($submission2)) {
            // Results match - auto-validate
            $this->validateMatch($match, $submission1);
        } else {
            // Results conflict - create dispute
            $this->createDispute($match, $submission1, $submission2);
        }
    }

    /**
     * Validate a match with agreed-upon result.
     */
    private function validateMatch(TournamentMatch $match, MatchSubmission $submission): void
    {
        $match->complete($submission->toResultArray());
        $match->addDisputeHistoryEntry('validated', [
            'winnerId' => $submission->getClaimedWinnerId(),
            'score' => [$submission->getPlayer1Score(), $submission->getPlayer2Score()],
            'method' => 'auto-concordance',
        ]);

        $this->entityManager->flush();

        // Check if round should be completed
        $this->checkAndCompleteRound($match);
    }

    /**
     * Create a dispute for conflicting submissions.
     */
    private function createDispute(
        TournamentMatch $match,
        MatchSubmission $submission1,
        MatchSubmission $submission2
    ): void {
        $match->dispute();
        $match->addDisputeHistoryEntry('created', [
            'reason' => 'conflicting_submissions',
            'submission1' => [
                'submittedBy' => $submission1->getSubmittedBy()->getId(),
                'claimedWinner' => $submission1->getClaimedWinnerId(),
                'score' => [$submission1->getPlayer1Score(), $submission1->getPlayer2Score()],
            ],
            'submission2' => [
                'submittedBy' => $submission2->getSubmittedBy()->getId(),
                'claimedWinner' => $submission2->getClaimedWinnerId(),
                'score' => [$submission2->getPlayer1Score(), $submission2->getPlayer2Score()],
            ],
        ]);

        $this->entityManager->flush();
    }

    /**
     * Find the registration for a user in a match.
     */
    private function findPlayerRegistration(TournamentMatch $match, User $user): ?Registration
    {
        $player1 = $match->getPlayer1();
        if ($player1->getPlayer() === $user) {
            return $player1;
        }

        $player2 = $match->getPlayer2();
        if ($player2 !== null && $player2->getPlayer() === $user) {
            return $player2;
        }

        return null;
    }

    /**
     * Get player's pending submission for a match (if waiting for opponent).
     */
    public function getPlayerSubmission(TournamentMatch $match, User $user): ?MatchSubmission
    {
        return $this->submissionRepository->findByMatchAndUser($match, $user);
    }

    /**
     * Check if a user can submit a result for a match.
     */
    public function canUserSubmit(TournamentMatch $match, User $user): bool
    {
        try {
            $this->validateSubmission($match, $user);

            return true;
        } catch (InvalidSubmissionException) {
            return false;
        }
    }

    /**
     * Check if a user is the organizer of the tournament for a match.
     */
    public function isOrganizer(TournamentMatch $match, User $user): bool
    {
        $tournament = $match->getRound()->getTournament();
        $organizer = $tournament->getOrganizer();

        return $organizer !== null && $organizer->getId() === $user->getId();
    }

    /**
     * Check if a user can submit/override as organizer.
     */
    public function canOrganizerOverride(TournamentMatch $match, User $user): bool
    {
        // Must be organizer
        if (!$this->isOrganizer($match, $user)) {
            return false;
        }

        // Match must not be completed (or we allow override of completed matches)
        // For now, we allow override even on completed matches
        // But not BYE matches
        if ($match->isByeMatch()) {
            return false;
        }

        return true;
    }

    /**
     * Resolve a disputed match by forcing a result.
     *
     * @param TournamentMatch $match    The disputed match
     * @param User            $resolver The user resolving (organizer/admin)
     * @param int|null        $winnerId The winner's registration ID (null for tie)
     * @param int             $player1Score Player 1's final score
     * @param int             $player2Score Player 2's final score
     */
    public function resolveDispute(
        TournamentMatch $match,
        User $resolver,
        ?int $winnerId,
        int $player1Score,
        int $player2Score
    ): void {
        if (!$match->isDisputed()) {
            throw new \LogicException('Match is not disputed.');
        }

        $match->complete([
            'winnerId' => $winnerId,
            'player1Score' => $player1Score,
            'player2Score' => $player2Score,
        ]);

        $match->addDisputeHistoryEntry('resolved', [
            'resolvedBy' => $resolver->getId(),
            'winnerId' => $winnerId,
            'score' => [$player1Score, $player2Score],
            'method' => 'organizer_override',
        ]);

        $this->entityManager->flush();

        // Check if round should be completed
        $this->checkAndCompleteRound($match);
    }

    /**
     * Force a result on any match (organizer override).
     *
     * @param TournamentMatch $match    The match
     * @param User            $resolver The user forcing (organizer/admin)
     * @param int|null        $winnerId The winner's registration ID (null for tie)
     * @param int             $player1Score Player 1's final score
     * @param int             $player2Score Player 2's final score
     */
    public function forceResult(
        TournamentMatch $match,
        User $resolver,
        ?int $winnerId,
        int $player1Score,
        int $player2Score
    ): void {
        if ($match->isByeMatch()) {
            throw new \LogicException('Cannot override a BYE match.');
        }

        $previousResult = $match->getResult();
        $previousStatus = $match->getStatus();

        $match->complete([
            'winnerId' => $winnerId,
            'player1Score' => $player1Score,
            'player2Score' => $player2Score,
        ]);

        $match->addDisputeHistoryEntry('forced', [
            'forcedBy' => $resolver->getId(),
            'winnerId' => $winnerId,
            'score' => [$player1Score, $player2Score],
            'previousResult' => $previousResult,
            'previousStatus' => $previousStatus->value,
        ]);

        $this->entityManager->flush();

        // Check if round should be completed
        $this->checkAndCompleteRound($match);
    }

    /**
     * Check if all matches in the round are completed and update round status.
     */
    private function checkAndCompleteRound(TournamentMatch $match): void
    {
        $round = $match->getRound();

        // Only check if round is still ongoing
        if (!$round->isOngoing()) {
            return;
        }

        // Check if all matches are completed
        if ($round->areAllMatchesCompleted()) {
            $round->complete();
            $this->entityManager->flush();
        }
    }
}
