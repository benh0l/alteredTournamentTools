<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TournamentMatch;
use App\Enum\MatchStatus;
use App\Repository\TournamentMatchRepository;
use App\Repository\TournamentRepository;
use App\Security\Voter\TournamentVoter;
use App\Service\ResultSubmissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for organizer dispute resolution.
 */
#[Route('/tournaments/{tournamentId}/disputes', requirements: ['tournamentId' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class DisputeController extends AbstractController
{
    public function __construct(
        private TournamentMatchRepository $matchRepository,
        private TournamentRepository $tournamentRepository,
        private ResultSubmissionService $resultSubmissionService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * List all disputed matches in a tournament.
     */
    #[Route('', name: 'dispute_list', methods: ['GET'])]
    public function list(int $tournamentId): Response
    {
        $tournament = $this->tournamentRepository->find($tournamentId);
        if ($tournament === null) {
            throw $this->createNotFoundException('Tournoi non trouve.');
        }

        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        $disputes = $this->matchRepository->findBy([
            'status' => MatchStatus::DISPUTE,
        ]);

        // Filter to only this tournament's disputes
        $disputes = array_filter(
            $disputes,
            fn (TournamentMatch $match) => $match->getRound()->getTournament()->getId() === $tournament->getId()
        );

        return $this->render('dispute/list.html.twig', [
            'tournament' => $tournament,
            'disputes' => $disputes,
        ]);
    }

    /**
     * View a specific dispute with both submissions.
     */
    #[Route('/{matchId}', name: 'dispute_show', methods: ['GET'], requirements: ['matchId' => '\d+'])]
    public function show(int $tournamentId, int $matchId): Response
    {
        $tournament = $this->tournamentRepository->find($tournamentId);
        if ($tournament === null) {
            throw $this->createNotFoundException('Tournoi non trouve.');
        }

        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        $match = $this->matchRepository->find($matchId);

        if ($match === null || $match->getRound()->getTournament()->getId() !== $tournament->getId()) {
            throw $this->createNotFoundException('Match non trouve.');
        }

        return $this->render('dispute/show.html.twig', [
            'tournament' => $tournament,
            'match' => $match,
            'submissions' => $match->getSubmissions()->toArray(),
        ]);
    }

    /**
     * Resolve a disputed match.
     */
    #[Route('/{matchId}/resolve', name: 'dispute_resolve', methods: ['POST'], requirements: ['matchId' => '\d+'])]
    public function resolve(Request $request, int $tournamentId, int $matchId): Response
    {
        $tournament = $this->tournamentRepository->find($tournamentId);
        if ($tournament === null) {
            throw $this->createNotFoundException('Tournoi non trouve.');
        }

        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        $match = $this->matchRepository->find($matchId);

        if ($match === null || $match->getRound()->getTournament()->getId() !== $tournament->getId()) {
            throw $this->createNotFoundException('Match non trouve.');
        }

        if (!$match->isDisputed()) {
            $this->addFlash('warning', $this->translator->trans('flash.warning.match_not_in_dispute'));

            return $this->redirectToRoute('dispute_list', ['tournamentId' => $tournament->getId()]);
        }

        // CSRF validation
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('dispute_resolve_' . $match->getId(), $submittedToken)) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_security_token'));

            return $this->redirectToRoute('dispute_show', [
                'tournamentId' => $tournament->getId(),
                'matchId' => $match->getId(),
            ]);
        }

        // Get form data
        $winnerId = (int) $request->request->get('winner_id');
        $player1Score = (int) $request->request->get('player1_score');
        $player2Score = (int) $request->request->get('player2_score');

        // Validate winner is a participant
        $player1Id = $match->getPlayer1()->getId();
        $player2Id = $match->getPlayer2()?->getId();

        if ($winnerId !== $player1Id && $winnerId !== $player2Id) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_winner'));

            return $this->redirectToRoute('dispute_show', [
                'tournamentId' => $tournament->getId(),
                'matchId' => $match->getId(),
            ]);
        }

        try {
            $this->resultSubmissionService->resolveDispute(
                $match,
                $this->getUser(),
                $winnerId,
                $player1Score,
                $player2Score
            );

            $this->addFlash('success', $this->translator->trans('flash.success.dispute_resolved'));

            return $this->redirectToRoute('round_show', [
                'id' => $tournament->getId(),
                'roundNumber' => $match->getRound()->getRoundNumber(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('dispute_show', [
                'tournamentId' => $tournament->getId(),
                'matchId' => $match->getId(),
            ]);
        }
    }

    /**
     * Force a result on any match (organizer override).
     */
    #[Route('/{matchId}/force', name: 'dispute_force', methods: ['POST'], requirements: ['matchId' => '\d+'])]
    public function force(Request $request, int $tournamentId, int $matchId): Response
    {
        $tournament = $this->tournamentRepository->find($tournamentId);
        if ($tournament === null) {
            throw $this->createNotFoundException('Tournoi non trouve.');
        }

        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        $match = $this->matchRepository->find($matchId);

        if ($match === null || $match->getRound()->getTournament()->getId() !== $tournament->getId()) {
            throw $this->createNotFoundException('Match non trouve.');
        }

        if ($match->isByeMatch()) {
            $this->addFlash('warning', $this->translator->trans('flash.warning.cannot_force_bye'));

            return $this->redirectToRoute('round_show', [
                'id' => $tournament->getId(),
                'roundNumber' => $match->getRound()->getRoundNumber(),
            ]);
        }

        // CSRF validation
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('dispute_force_' . $match->getId(), $submittedToken)) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_security_token'));

            return $this->redirectToRoute('round_show', [
                'id' => $tournament->getId(),
                'roundNumber' => $match->getRound()->getRoundNumber(),
            ]);
        }

        // Get form data
        $winnerId = (int) $request->request->get('winner_id');
        $player1Score = (int) $request->request->get('player1_score');
        $player2Score = (int) $request->request->get('player2_score');

        // Validate winner is a participant
        $player1Id = $match->getPlayer1()->getId();
        $player2Id = $match->getPlayer2()?->getId();

        if ($winnerId !== $player1Id && $winnerId !== $player2Id) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_winner'));

            return $this->redirectToRoute('round_show', [
                'id' => $tournament->getId(),
                'roundNumber' => $match->getRound()->getRoundNumber(),
            ]);
        }

        try {
            $this->resultSubmissionService->forceResult(
                $match,
                $this->getUser(),
                $winnerId,
                $player1Score,
                $player2Score
            );

            $this->addFlash('success', $this->translator->trans('flash.success.match_result_forced'));

            return $this->redirectToRoute('round_show', [
                'id' => $tournament->getId(),
                'roundNumber' => $match->getRound()->getRoundNumber(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('round_show', [
                'id' => $tournament->getId(),
                'roundNumber' => $match->getRound()->getRoundNumber(),
            ]);
        }
    }
}
