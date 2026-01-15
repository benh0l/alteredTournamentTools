<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\TournamentStatus;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;
use App\Repository\TournamentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin dashboard controller (FR68).
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly TournamentMatchRepository $matchRepository,
    ) {
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        // Get all active tournaments with player counts
        $activeTournaments = $this->tournamentRepository->findAllActiveWithDetails();

        // Calculate stats
        $ongoingCount = 0;
        $publishedCount = 0;
        $totalPlayers = 0;

        foreach ($activeTournaments as $data) {
            $tournament = $data['tournament'];
            $playerCount = $data['playerCount'];
            $totalPlayers += $playerCount;

            if ($tournament->getStatus() === TournamentStatus::ONGOING) {
                $ongoingCount++;
            } else {
                $publishedCount++;
            }
        }

        // Count pending disputes across all active tournaments
        $pendingDisputes = $this->matchRepository->countAllPendingDisputes();

        return $this->render('admin/dashboard.html.twig', [
            'tournaments' => $activeTournaments,
            'stats' => [
                'ongoing' => $ongoingCount,
                'published' => $publishedCount,
                'totalActive' => count($activeTournaments),
                'totalPlayers' => $totalPlayers,
                'pendingDisputes' => $pendingDisputes,
            ],
        ]);
    }

    #[Route('/tournament/{id}', name: 'admin_tournament_details')]
    public function tournamentDetails(int $id): Response
    {
        $tournament = $this->tournamentRepository->findWithAllDetails($id);

        if (!$tournament) {
            throw $this->createNotFoundException('Tournament not found');
        }

        $registrations = $this->registrationRepository->findByTournamentWithPlayer($tournament);
        $disputes = $this->matchRepository->findDisputes($tournament);

        // Get matches organized by round
        $rounds = [];
        foreach ($tournament->getRounds() as $round) {
            $matches = $this->matchRepository->findByRoundWithPlayers($round);
            $rounds[] = [
                'round' => $round,
                'matches' => $matches,
            ];
        }

        return $this->render('admin/tournament_details.html.twig', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'disputes' => $disputes,
            'rounds' => $rounds,
        ]);
    }

    #[Route('/tournament/{id}/export', name: 'admin_tournament_export')]
    public function exportTournament(int $id): Response
    {
        $tournament = $this->tournamentRepository->findWithAllDetails($id);

        if (!$tournament) {
            throw $this->createNotFoundException('Tournament not found');
        }

        // Build export data
        $data = [
            'id' => $tournament->getId(),
            'name' => $tournament->getName(),
            'status' => $tournament->getStatus()->value,
            'format' => $tournament->getFormat()->value,
            'structure' => $tournament->getStructure()->value,
            'visibility' => $tournament->getVisibility()->value,
            'date' => $tournament->getDate()->format('Y-m-d'),
            'time' => $tournament->getTime()?->format('H:i'),
            'maxPlayers' => $tournament->getMaxPlayers(),
            'nbRounds' => $tournament->getNbRounds(),
            'createdAt' => $tournament->getCreatedAt()->format('c'),
            'organizer' => [
                'id' => $tournament->getOrganizer()->getId(),
                'pseudo' => $tournament->getOrganizer()->getPseudo(),
                'email' => $tournament->getOrganizer()->getEmail(),
            ],
            'registrations' => [],
            'rounds' => [],
        ];

        // Add registrations
        foreach ($tournament->getRegistrations() as $reg) {
            $data['registrations'][] = [
                'player_id' => $reg->getPlayer()->getId(),
                'pseudo' => $reg->getPlayer()->getPseudo(),
                'email' => $reg->getPlayer()->getEmail(),
                'registeredAt' => $reg->getRegisteredAt()->format('c'),
                'decklistUrl' => $reg->getDecklistUrl(),
            ];
        }

        // Add rounds and matches
        foreach ($tournament->getRounds() as $round) {
            $roundData = [
                'roundNumber' => $round->getRoundNumber(),
                'type' => $round->getType(),
                'startedAt' => $round->getStartedAt()?->format('c'),
                'endedAt' => $round->getEndedAt()?->format('c'),
                'matches' => [],
            ];

            $matches = $this->matchRepository->findByRoundWithPlayers($round);
            foreach ($matches as $match) {
                $matchData = [
                    'id' => $match->getId(),
                    'tableNumber' => $match->getTableNumber(),
                    'status' => $match->getStatus()->value,
                    'isBye' => $match->isByeMatch(),
                    'player1' => $match->getPlayer1()?->getPlayer()->getPseudo(),
                    'player2' => $match->getPlayer2()?->getPlayer()->getPseudo(),
                    'winner' => $match->getWinner()?->getPlayer()->getPseudo(),
                    'disputeHistory' => $match->getDisputeHistory(),
                ];
                $roundData['matches'][] = $matchData;
            }

            $data['rounds'][] = $roundData;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return new Response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="tournament-' . $tournament->getId() . '.json"',
        ]);
    }
}
