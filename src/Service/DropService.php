<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\TournamentMatch;
use App\Enum\MatchFormat;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Event\PlayerDroppedEvent;
use App\Exception\DropException;
use App\Repository\TournamentMatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Service for handling player drops (voluntary tournament withdrawal).
 *
 * A drop removes a player from future pairings while keeping them in standings.
 */
final class DropService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TournamentMatchRepository $matchRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly StandingsService $standingsService,
    ) {
    }

    /**
     * Drop a player from a tournament.
     *
     * @param Registration $registration      The player's registration
     * @param bool         $forfeitActiveMatch If true, forfeit any active match before dropping
     * @param string       $reason            Optional reason for the drop
     * @param bool         $byOrganizer       Whether the drop was initiated by the organizer
     *
     * @throws DropException
     */
    public function dropPlayer(
        Registration $registration,
        bool $forfeitActiveMatch = false,
        string $reason = '',
        bool $byOrganizer = false
    ): void {
        $tournament = $registration->getTournament();

        // Validate tournament is ongoing
        if ($tournament->getStatus() !== TournamentStatus::ONGOING) {
            throw DropException::tournamentNotOngoing();
        }

        // Check if already dropped
        if ($registration->isDropped()) {
            throw DropException::alreadyDropped();
        }

        // Check for active match
        $activeMatch = $this->matchRepository->findActiveMatchForRegistration($registration);
        $hadActiveMatch = false;
        if ($activeMatch !== null) {
            if (!$forfeitActiveMatch) {
                throw DropException::activeMatchRequiresForfeit($activeMatch->getId() ?? 0);
            }
            $this->forfeitMatch($activeMatch, $registration);
            $hadActiveMatch = true;
        }

        // Perform the drop
        $registration->drop();
        $this->entityManager->flush();

        // Invalidate standings cache if match was forfeited
        if ($hadActiveMatch) {
            $this->standingsService->invalidateCache($tournament);
        }

        // Dispatch event for notifications
        $this->eventDispatcher->dispatch(
            new PlayerDroppedEvent(
                registration: $registration,
                reason: $reason,
                byOrganizer: $byOrganizer
            )
        );

        $this->logger->info('Player dropped from tournament', [
            'tournament_id' => $tournament->getId(),
            'tournament_name' => $tournament->getName(),
            'player_id' => $registration->getPlayer()->getId(),
            'player_pseudo' => $registration->getPlayer()->getPseudo(),
            'by_organizer' => $byOrganizer,
            'reason' => $reason,
        ]);
    }

    /**
     * Reverse a drop action (organizer only).
     *
     * @throws DropException
     */
    public function undropPlayer(Registration $registration): void
    {
        if (!$registration->isDropped()) {
            throw DropException::notDropped();
        }

        $registration->undrop();
        $this->entityManager->flush();

        $this->logger->info('Player undropped from tournament', [
            'tournament_id' => $registration->getTournament()->getId(),
            'tournament_name' => $registration->getTournament()->getName(),
            'player_id' => $registration->getPlayer()->getId(),
            'player_pseudo' => $registration->getPlayer()->getPseudo(),
        ]);
    }

    /**
     * Check if a player has an active match.
     */
    public function hasActiveMatch(Registration $registration): bool
    {
        return $this->matchRepository->findActiveMatchForRegistration($registration) !== null;
    }

    /**
     * Get the active match for a registration, if any.
     */
    public function getActiveMatch(Registration $registration): ?TournamentMatch
    {
        return $this->matchRepository->findActiveMatchForRegistration($registration);
    }

    /**
     * Forfeit a match due to player drop.
     * The opponent wins with the maximum score for the format.
     */
    private function forfeitMatch(TournamentMatch $match, Registration $droppedPlayer): void
    {
        // Handle bye case (opponent is null)
        if ($match->isByeMatch()) {
            $match->setStatus(MatchStatus::COMPLETED);
            $match->setCompletedAt(new \DateTimeImmutable());

            return;
        }

        // Determine opponent
        $opponent = $match->getOpponent($droppedPlayer);
        if ($opponent === null) {
            // Should not happen, but handle gracefully
            $match->setStatus(MatchStatus::COMPLETED);
            $match->setCompletedAt(new \DateTimeImmutable());

            return;
        }

        // Determine score based on match format
        $tournament = $match->getRound()->getTournament();
        $format = $tournament->getMatchFormatForRound($match->getRound());
        $winScore = $format === MatchFormat::BO1 ? 1 : 2;

        // Set result with opponent as winner
        $isDroppedPlayerPlayer1 = $match->getPlayer1() === $droppedPlayer;
        $player1Score = $isDroppedPlayerPlayer1 ? 0 : $winScore;
        $player2Score = $isDroppedPlayerPlayer1 ? $winScore : 0;

        $match->setResult([
            'winnerId' => $opponent->getId(),
            'player1Score' => $player1Score,
            'player2Score' => $player2Score,
            'forfeit' => true,
            'forfeitReason' => 'drop',
        ]);
        $match->setStatus(MatchStatus::COMPLETED);
        $match->setCompletedAt(new \DateTimeImmutable());

        // Add to dispute history for traceability
        $match->addDisputeHistoryEntry('forfeit', [
            'reason' => 'player_drop',
            'droppedPlayerId' => $droppedPlayer->getId(),
            'winnerId' => $opponent->getId(),
        ]);

        $this->logger->info('Match forfeited due to player drop', [
            'match_id' => $match->getId(),
            'dropped_player_id' => $droppedPlayer->getId(),
            'winner_id' => $opponent->getId(),
        ]);
    }
}
