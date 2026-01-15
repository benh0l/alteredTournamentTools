<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Registration;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;

/**
 * Service for tracking and summarizing dispute history per player.
 */
class DisputeHistoryService
{
    public function __construct(
        private TournamentMatchRepository $matchRepository,
        private RegistrationRepository $registrationRepository,
    ) {
    }

    /**
     * Get dispute summary for a user across all tournaments.
     *
     * @return array{
     *     totalDisputes: int,
     *     resolvedInFavor: int,
     *     resolvedAgainst: int,
     *     matches: array<int, array{
     *         matchId: int,
     *         tournamentName: string,
     *         roundNumber: int,
     *         opponent: string,
     *         result: string,
     *         resolvedAt: string|null,
     *         wasInFavor: bool|null
     *     }>
     * }
     */
    public function getDisputeSummaryForUser(User $user): array
    {
        // Get all registrations for this user
        $registrations = $this->registrationRepository->findBy(['player' => $user]);

        $totalDisputes = 0;
        $resolvedInFavor = 0;
        $resolvedAgainst = 0;
        $matches = [];

        foreach ($registrations as $registration) {
            $disputedMatches = $this->matchRepository->findDisputedMatchesForPlayer($registration);

            foreach ($disputedMatches as $match) {
                $totalDisputes++;

                $matchSummary = $this->createMatchSummary($match, $registration);
                $matches[] = $matchSummary;

                if ($matchSummary['wasInFavor'] === true) {
                    $resolvedInFavor++;
                } elseif ($matchSummary['wasInFavor'] === false) {
                    $resolvedAgainst++;
                }
            }
        }

        // Sort by most recent first
        usort($matches, fn ($a, $b) => ($b['resolvedAt'] ?? '') <=> ($a['resolvedAt'] ?? ''));

        return [
            'totalDisputes' => $totalDisputes,
            'resolvedInFavor' => $resolvedInFavor,
            'resolvedAgainst' => $resolvedAgainst,
            'matches' => $matches,
        ];
    }

    /**
     * Get dispute summary for a registration in a specific tournament.
     *
     * @return array{
     *     totalDisputes: int,
     *     matches: TournamentMatch[]
     * }
     */
    public function getDisputeSummaryForRegistration(Registration $registration): array
    {
        $disputedMatches = $this->matchRepository->findDisputedMatchesForPlayer($registration);

        return [
            'totalDisputes' => count($disputedMatches),
            'matches' => $disputedMatches,
        ];
    }

    /**
     * Create a summary array for a disputed match.
     *
     * @return array{
     *     matchId: int,
     *     tournamentName: string,
     *     roundNumber: int,
     *     opponent: string,
     *     result: string,
     *     resolvedAt: string|null,
     *     wasInFavor: bool|null
     * }
     */
    private function createMatchSummary(TournamentMatch $match, Registration $registration): array
    {
        $opponent = $match->getOpponent($registration);
        $history = $match->getDisputeHistory() ?? [];

        // Find when it was resolved
        $resolvedAt = null;
        foreach ($history as $entry) {
            if ($entry['type'] === 'resolved') {
                $resolvedAt = $entry['timestamp'];
                break;
            }
        }

        // Check if resolved in player's favor
        $wasInFavor = null;
        if ($match->isCompleted()) {
            $winner = $match->getWinner();
            $wasInFavor = $winner !== null && $winner->getId() === $registration->getId();
        }

        // Determine result string
        $result = 'En cours';
        if ($match->isCompleted()) {
            $result = $wasInFavor ? 'Victoire' : 'Defaite';
        } elseif ($match->isDisputed()) {
            $result = 'En litige';
        }

        return [
            'matchId' => $match->getId() ?? 0,
            'tournamentName' => $match->getRound()->getTournament()->getName(),
            'roundNumber' => $match->getRound()->getRoundNumber(),
            'opponent' => $opponent?->getPlayer()->getPseudo() ?? 'BYE',
            'result' => $result,
            'resolvedAt' => $resolvedAt,
            'wasInFavor' => $wasInFavor,
        ];
    }

    /**
     * Check if a user has a concerning dispute pattern.
     *
     * A user is flagged if they have a high ratio of disputes.
     * This can help organizers identify potential bad actors.
     *
     * @return array{isFlagged: bool, reason: string|null, stats: array{total: int, ratio: float}}
     */
    public function checkDisputePattern(User $user): array
    {
        $registrations = $this->registrationRepository->findBy(['player' => $user]);

        $totalMatches = 0;
        $totalDisputes = 0;

        foreach ($registrations as $registration) {
            $allMatches = $this->matchRepository->findByPlayer($registration);
            $disputedMatches = $this->matchRepository->findDisputedMatchesForPlayer($registration);

            $totalMatches += count($allMatches);
            $totalDisputes += count($disputedMatches);
        }

        if ($totalMatches === 0) {
            return [
                'isFlagged' => false,
                'reason' => null,
                'stats' => ['total' => 0, 'ratio' => 0.0],
            ];
        }

        $ratio = $totalDisputes / $totalMatches;

        // Flag if more than 20% of matches end in disputes and at least 3 disputes
        $isFlagged = $ratio > 0.20 && $totalDisputes >= 3;

        return [
            'isFlagged' => $isFlagged,
            'reason' => $isFlagged ? sprintf(
                'Ratio de litiges eleve: %d litiges sur %d matchs (%.0f%%)',
                $totalDisputes,
                $totalMatches,
                $ratio * 100
            ) : null,
            'stats' => [
                'total' => $totalDisputes,
                'ratio' => $ratio,
            ],
        ];
    }
}
