<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Enum\MatchStatus;
use App\Repository\TournamentMatchRepository;
use App\Security\Voter\TournamentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for the tournament dashboard.
 *
 * FR42: Organizers view real-time dashboard of ongoing tournament
 * FR43: Dashboard displays round timer with color codes (Green → Orange → Red)
 * FR44: Dashboard displays live statistics (Matches completed / ongoing / disputes)
 * FR45: Dashboard displays detailed list of round matches with statuses
 * FR46: Organizers view pairings of any round
 * FR47: Dashboard automatically updates via push (SSE) without manual refresh
 *
 * Public tournaments (ONGOING/COMPLETED) are viewable by anyone.
 * Private tournaments are viewable by organizer and registered players.
 */
#[Route('/tournaments/{id}/dashboard', requirements: ['id' => '\d+'])]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TournamentMatchRepository $matchRepository,
    ) {
    }

    /**
     * Main dashboard page.
     * Accessible to organizers, registered players, or anyone for public tournaments.
     */
    #[Route('', name: 'tournament_dashboard', methods: ['GET'])]
    public function index(Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::VIEW_DASHBOARD, $tournament);

        $currentRound = $tournament->getCurrentRound();
        $latestRound = $tournament->getLatestRound();
        $statistics = $this->calculateRoundStatistics($tournament);

        // If no ongoing round but there's a completed round, use that for display
        $displayRound = $currentRound ?? $latestRound;

        // Check if user can manage (organizer) to show/hide management buttons
        $canManage = $this->isGranted(TournamentVoter::MANAGE, $tournament);

        return $this->render('dashboard/index.html.twig', [
            'tournament' => $tournament,
            'current_round' => $currentRound,
            'latest_round' => $latestRound,
            'display_round' => $displayRound,
            'statistics' => $statistics,
            'rounds' => $tournament->getRounds(),
            'can_manage' => $canManage,
        ]);
    }

    /**
     * View a specific round's matches (historical or current).
     */
    #[Route('/round/{roundNumber}', name: 'tournament_dashboard_round', methods: ['GET'], requirements: ['roundNumber' => '\d+'])]
    public function viewRound(Tournament $tournament, int $roundNumber): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::VIEW_DASHBOARD, $tournament);

        $round = null;
        foreach ($tournament->getRounds() as $r) {
            if ($r->getRoundNumber() === $roundNumber) {
                $round = $r;
                break;
            }
        }

        if ($round === null) {
            throw $this->createNotFoundException(sprintf(
                'Ronde %d non trouvee pour ce tournoi.',
                $roundNumber
            ));
        }

        $matches = $this->matchRepository->findByRoundWithPlayers($round);
        $canManage = $this->isGranted(TournamentVoter::MANAGE, $tournament);

        return $this->render('dashboard/round.html.twig', [
            'tournament' => $tournament,
            'round' => $round,
            'matches' => $matches,
            'current_round' => $tournament->getCurrentRound(),
            'rounds' => $tournament->getRounds(),
            'can_manage' => $canManage,
        ]);
    }

    /**
     * Calculate statistics for the current or latest round.
     *
     * @return array{
     *     total_matches: int,
     *     completed_matches: int,
     *     ongoing_matches: int,
     *     pending_matches: int,
     *     disputed_matches: int,
     *     bye_matches: int,
     *     completion_percentage: float
     * }
     */
    private function calculateRoundStatistics(Tournament $tournament): array
    {
        // Use current round if ongoing, otherwise use latest round
        $round = $tournament->getCurrentRound() ?? $tournament->getLatestRound();

        if ($round === null) {
            return [
                'total_matches' => 0,
                'completed_matches' => 0,
                'ongoing_matches' => 0,
                'pending_matches' => 0,
                'disputed_matches' => 0,
                'bye_matches' => 0,
                'completion_percentage' => 0.0,
            ];
        }

        $matches = $round->getMatches();
        $total = $matches->count();
        $completed = 0;
        $ongoing = 0;
        $pending = 0;
        $disputed = 0;
        $bye = 0;

        foreach ($matches as $match) {
            if ($match->isByeMatch()) {
                $bye++;
                $completed++; // BYE matches are auto-completed
                continue;
            }

            match ($match->getStatus()) {
                MatchStatus::COMPLETED => $completed++,
                MatchStatus::ONGOING => $ongoing++,
                MatchStatus::PENDING => $pending++,
                MatchStatus::DISPUTE => $disputed++,
            };
        }

        $playableMatches = $total - $bye;
        $completionPercentage = $playableMatches > 0
            ? ($completed - $bye) / $playableMatches * 100
            : 100.0;

        return [
            'total_matches' => $total,
            'completed_matches' => $completed,
            'ongoing_matches' => $ongoing,
            'pending_matches' => $pending,
            'disputed_matches' => $disputed,
            'bye_matches' => $bye,
            'completion_percentage' => round($completionPercentage, 1),
        ];
    }
}
