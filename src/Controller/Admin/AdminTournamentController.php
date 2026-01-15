<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Message\SendAdminEmailMessage;
use App\Repository\TournamentMatchRepository;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tournament management controller (FR72).
 */
#[Route('/admin/tournament')]
#[IsGranted('ROLE_ADMIN')]
class AdminTournamentController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly TournamentMatchRepository $matchRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/{id}/matches', name: 'admin_tournament_matches')]
    public function matches(int $id): Response
    {
        $tournament = $this->tournamentRepository->find($id);

        if (!$tournament) {
            throw $this->createNotFoundException('Tournament not found');
        }

        // Get all rounds with matches
        $rounds = [];
        foreach ($tournament->getRounds() as $round) {
            $matches = $this->matchRepository->findByRoundWithPlayers($round);
            $rounds[] = [
                'round' => $round,
                'matches' => $matches,
            ];
        }

        return $this->render('admin/tournament/matches.html.twig', [
            'tournament' => $tournament,
            'rounds' => $rounds,
        ]);
    }

    #[Route('/match/{id}/override', name: 'admin_match_override', methods: ['POST'])]
    public function override(int $id, Request $request): JsonResponse
    {
        $match = $this->matchRepository->find($id);

        if (!$match) {
            return new JsonResponse(['error' => 'Match not found'], 404);
        }

        // Validate CSRF
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_override', $csrfToken)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $winner = $request->request->get('winner');
        $reason = $request->request->get('reason');

        if (empty($winner) || empty($reason)) {
            return new JsonResponse(['error' => 'Winner and reason are required'], 400);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        // Determine winner registration
        $winnerRegistration = null;
        if ($winner === 'player_1') {
            $winnerRegistration = $match->getPlayer1();
        } elseif ($winner === 'player_2') {
            $winnerRegistration = $match->getPlayer2();
        }
        // If 'draw' or null, no winner set

        // Update match
        if ($winnerRegistration !== null) {
            $match->setWinner($winnerRegistration);
        }
        $match->setStatus(MatchStatus::COMPLETED);

        // Log override in dispute history
        $history = $match->getDisputeHistory() ?? [];
        $history[] = [
            'type' => 'admin_override',
            'admin_id' => $admin->getId(),
            'admin_name' => $admin->getPseudo(),
            'winner' => $winner,
            'reason' => $reason,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];
        $match->setDisputeHistory($history);

        $this->entityManager->flush();

        // Send notifications
        $this->sendOverrideNotifications($match, $admin, $winner, $reason);

        return new JsonResponse([
            'success' => true,
            'message' => 'Resultat du match mis a jour',
        ]);
    }

    private function sendOverrideNotifications(
        TournamentMatch $match,
        User $admin,
        string $winner,
        string $reason
    ): void {
        $round = $match->getRound();
        $tournament = $round->getTournament();
        $tableNumber = $match->getTableNumber();

        // Determine winner name
        $winnerName = 'Egalite';
        if ($winner === 'player_1' && $match->getPlayer1()) {
            $winnerName = $match->getPlayer1()->getPlayer()->getPseudo();
        } elseif ($winner === 'player_2' && $match->getPlayer2()) {
            $winnerName = $match->getPlayer2()->getPlayer()->getPseudo();
        }

        $subject = "Decision administrative - Match Table {$tableNumber}";
        $playerBody = <<<BODY
Le resultat de votre match (Table {$tableNumber}) dans le tournoi "{$tournament->getName()}" a ete determine par l'administration.

Resultat: {$winnerName} gagne

Si vous avez des questions, veuillez contacter le support.
BODY;

        // Notify Player 1
        if ($match->getPlayer1()) {
            $player1 = $match->getPlayer1()->getPlayer();
            $this->messageBus->dispatch(new SendAdminEmailMessage(
                $player1->getEmail(),
                $player1->getPseudo(),
                $subject,
                $playerBody,
                $admin->getPseudo()
            ));
        }

        // Notify Player 2
        if ($match->getPlayer2()) {
            $player2 = $match->getPlayer2()->getPlayer();
            $this->messageBus->dispatch(new SendAdminEmailMessage(
                $player2->getEmail(),
                $player2->getPseudo(),
                $subject,
                $playerBody,
                $admin->getPseudo()
            ));
        }

        // Notify Organizer
        $organizer = $tournament->getOrganizer();
        $organizerBody = <<<BODY
L'administration a intervenu dans un match de votre tournoi "{$tournament->getName()}".

Match: Table {$tableNumber} (Ronde {$round->getRoundNumber()})
Decision: {$winnerName} gagne
Raison: {$reason}

Aucune action n'est requise de votre part.
BODY;

        $this->messageBus->dispatch(new SendAdminEmailMessage(
            $organizer->getEmail(),
            $organizer->getPseudo(),
            "Intervention administrative - {$tournament->getName()}",
            $organizerBody,
            $admin->getPseudo()
        ));
    }
}
