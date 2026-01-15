<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;
use App\Service\StandingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for player tournament experience.
 *
 * FR49: Players view current round pairings.
 * FR50: Players view live tournament standings.
 * FR52: Players view previous rounds results.
 * FR53: System displays live standings automatically after each match validation.
 */
#[Route('/tournaments/{id}', requirements: ['id' => '\d+'])]
final class PlayerController extends AbstractController
{
    public function __construct(
        private readonly TournamentMatchRepository $matchRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly StandingsService $standingsService,
    ) {
    }

    /**
     * View current round pairings (FR49).
     */
    #[Route('/pairings', name: 'tournament_pairings', methods: ['GET'])]
    public function pairings(Tournament $tournament): Response
    {
        $currentRound = $tournament->getCurrentRound();
        $matches = [];
        $playerRegistration = null;

        if ($currentRound !== null) {
            $matches = $this->matchRepository->findByRoundWithPlayers($currentRound);
        }

        // Find the current user's registration for highlighting
        $user = $this->getUser();
        if ($user !== null) {
            $playerRegistration = $this->registrationRepository->findOneBy([
                'player' => $user,
                'tournament' => $tournament,
            ]);
        }

        return $this->render('player/pairings.html.twig', [
            'tournament' => $tournament,
            'current_round' => $currentRound,
            'matches' => $matches,
            'player_registration' => $playerRegistration,
        ]);
    }

    /**
     * View live tournament standings (FR50, FR53).
     */
    #[Route('/standings', name: 'tournament_standings', methods: ['GET'])]
    public function standings(Tournament $tournament): Response
    {
        $standings = $this->standingsService->calculateStandings($tournament);

        // Find the current user's registration for highlighting
        $user = $this->getUser();
        $playerRegistration = null;

        if ($user !== null) {
            $playerRegistration = $this->registrationRepository->findOneBy([
                'player' => $user,
                'tournament' => $tournament,
            ]);
        }

        return $this->render('player/standings.html.twig', [
            'tournament' => $tournament,
            'standings' => $standings,
            'player_registration' => $playerRegistration,
        ]);
    }

    /**
     * View previous rounds results (FR52).
     */
    #[Route('/results', name: 'tournament_results', methods: ['GET'])]
    public function results(Tournament $tournament): Response
    {
        $rounds = $tournament->getRounds()->toArray();

        // Get matches for each round
        $roundsData = [];
        foreach ($rounds as $round) {
            $matches = $this->matchRepository->findByRoundWithPlayers($round);
            $roundsData[] = [
                'round' => $round,
                'matches' => $matches,
            ];
        }

        // Find the current user's registration for highlighting
        $user = $this->getUser();
        $playerRegistration = null;

        if ($user !== null) {
            $playerRegistration = $this->registrationRepository->findOneBy([
                'player' => $user,
                'tournament' => $tournament,
            ]);
        }

        return $this->render('player/results.html.twig', [
            'tournament' => $tournament,
            'rounds_data' => $roundsData,
            'player_registration' => $playerRegistration,
        ]);
    }
}
