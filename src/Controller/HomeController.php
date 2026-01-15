<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TournamentRepository;
use App\Service\PlayerMatchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Home controller with action-first interface.
 *
 * FR48: Players immediately see their ongoing match on home screen.
 * NFR30: Interface "action-first" affiche match en cours en < 1s après connexion joueur.
 */
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly PlayerMatchService $playerMatchService,
        private readonly TournamentRepository $tournamentRepository,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $currentMatch = null;
        $upcomingTournaments = [];
        $ongoingTournaments = [];

        if ($user !== null) {
            // Find the player's current ongoing match (action-first!)
            $currentMatch = $this->playerMatchService->findCurrentMatch($user);

            // Get upcoming tournaments where user is registered
            $upcomingTournaments = $this->playerMatchService->getUpcomingTournamentRegistrations($user, 10);

            // Get ongoing tournaments where player might not have a current match
            $ongoingTournaments = $this->playerMatchService->getOngoingTournamentRegistrations($user);
        }

        // Get public upcoming tournaments for discovery section
        $publicTournaments = $this->tournamentRepository->findUpcomingPublicTournaments();

        return $this->render('home/index.html.twig', [
            'current_match' => $currentMatch,
            'upcoming_tournaments' => $upcomingTournaments,
            'ongoing_tournaments' => $ongoingTournaments,
            'public_tournaments' => $publicTournaments,
        ]);
    }
}
