<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TournamentMatch;
use App\Enum\MatchFormat;
use App\Exception\InvalidSubmissionException;
use App\Repository\TournamentMatchRepository;
use App\Service\ResultSubmissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for match-related actions (result submission).
 */
#[Route('/match')]
#[IsGranted('ROLE_USER')]
final class MatchController extends AbstractController
{
    public function __construct(
        private TournamentMatchRepository $matchRepository,
        private ResultSubmissionService $resultSubmissionService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Display the result submission form for a match.
     */
    #[Route('/{id}/submit', name: 'match_submit_form', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function submitForm(TournamentMatch $match): Response
    {
        $user = $this->getUser();
        $isOrganizer = $this->resultSubmissionService->isOrganizer($match, $user);
        $canOrganizerOverride = $this->resultSubmissionService->canOrganizerOverride($match, $user);

        // Check if user can submit as player
        if (!$this->resultSubmissionService->canUserSubmit($match, $user)) {
            // If organizer, allow them to use the override form
            if ($canOrganizerOverride) {
                $matchFormat = $match->getRound()->getTournament()->getMatchFormatForRound($match->getRound());

                return $this->render('match/submit.html.twig', [
                    'match' => $match,
                    'is_organizer' => true,
                    'organizer_mode' => true,
                    'match_format' => $matchFormat,
                ]);
            }

            // Check specific reasons
            if ($match->isCompleted()) {
                // Even completed matches can be corrected by organizer
                if ($isOrganizer) {
                    $matchFormat = $match->getRound()->getTournament()->getMatchFormatForRound($match->getRound());

                    return $this->render('match/submit.html.twig', [
                        'match' => $match,
                        'is_organizer' => true,
                        'organizer_mode' => true,
                        'match_format' => $matchFormat,
                    ]);
                }

                $this->addFlash('info', $this->translator->trans('flash.info.match_already_completed'));

                return $this->redirectToRoute('round_show', [
                    'id' => $match->getRound()->getTournament()->getId(),
                    'roundNumber' => $match->getRound()->getRoundNumber(),
                ]);
            }

            if ($match->isByeMatch()) {
                $this->addFlash('warning', $this->translator->trans('flash.warning.cannot_submit_bye'));

                return $this->redirectToRoute('round_list', [
                    'id' => $match->getRound()->getTournament()->getId(),
                ]);
            }

            // Check if already submitted
            $existingSubmission = $this->resultSubmissionService->getPlayerSubmission($match, $user);
            if ($existingSubmission !== null) {
                return $this->render('match/waiting.html.twig', [
                    'match' => $match,
                    'submission' => $existingSubmission,
                    'is_organizer' => $isOrganizer,
                ]);
            }

            // User is not a participant
            $this->addFlash('error', $this->translator->trans('flash.error.not_match_participant'));

            return $this->redirectToRoute('round_list', [
                'id' => $match->getRound()->getTournament()->getId(),
            ]);
        }

        $matchFormat = $match->getRound()->getTournament()->getMatchFormatForRound($match->getRound());

        return $this->render('match/submit.html.twig', [
            'match' => $match,
            'is_organizer' => $isOrganizer,
            'organizer_mode' => false,
            'match_format' => $matchFormat,
        ]);
    }

    /**
     * Process a result submission.
     */
    #[Route('/{id}/submit', name: 'match_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submit(Request $request, TournamentMatch $match): Response
    {
        $user = $this->getUser();
        $isOrganizerOverride = $request->request->getBoolean('organizer_override', false);

        // CSRF validation
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('match_submit_' . $match->getId(), $submittedToken)) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_security_token'));

            return $this->redirectToRoute('match_submit_form', ['id' => $match->getId()]);
        }

        // Get form data
        $winnerIdRaw = $request->request->get('winner_id');
        // winner_id = 0 means tie
        $winnerId = ($winnerIdRaw === '0' || $winnerIdRaw === '') ? null : (int) $winnerIdRaw;
        $player1Score = (int) $request->request->get('player1_score');
        $player2Score = (int) $request->request->get('player2_score');

        // Validate winner is a participant (or null for tie)
        $player1Id = $match->getPlayer1()->getId();
        $player2Id = $match->getPlayer2()?->getId();

        if ($winnerId !== null && $winnerId !== $player1Id && $winnerId !== $player2Id) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_winner'));

            return $this->redirectToRoute('match_submit_form', ['id' => $match->getId()]);
        }

        // Reject ties for elimination rounds
        if ($winnerId === null && $match->getRound()->isEliminationRound()) {
            $this->addFlash('error', $this->translator->trans('flash.error.tie_not_allowed_elimination'));

            return $this->redirectToRoute('match_submit_form', ['id' => $match->getId()]);
        }

        // Get match format and validate/adjust scores accordingly
        $tournament = $match->getRound()->getTournament();
        $matchFormat = $tournament->getMatchFormatForRound($match->getRound());

        if ($matchFormat === MatchFormat::BO1) {
            // BO1: Auto-set score to 1-0 in favor of winner (or 0-0 for tie)
            if ($winnerId === null) {
                $player1Score = 0;
                $player2Score = 0;
            } elseif ($winnerId === $player1Id) {
                $player1Score = 1;
                $player2Score = 0;
            } else {
                $player1Score = 0;
                $player2Score = 1;
            }
        } elseif ($matchFormat === MatchFormat::BO3 && $winnerId !== null) {
            // BO3: Winner must have score of 2, loser must have 0 or 1
            $winnerScore = ($winnerId === $player1Id) ? $player1Score : $player2Score;
            $loserScore = ($winnerId === $player1Id) ? $player2Score : $player1Score;

            if ($winnerScore !== 2) {
                $this->addFlash('error', $this->translator->trans('flash.error.bo3_winner_must_have_2'));

                return $this->redirectToRoute('match_submit_form', ['id' => $match->getId()]);
            }

            if ($loserScore < 0 || $loserScore > 1) {
                $this->addFlash('error', $this->translator->trans('flash.error.bo3_loser_invalid_score'));

                return $this->redirectToRoute('match_submit_form', ['id' => $match->getId()]);
            }
        }

        // Handle organizer override - directly validate without waiting for players
        if ($isOrganizerOverride) {
            if (!$this->resultSubmissionService->canOrganizerOverride($match, $user)) {
                $this->addFlash('error', $this->translator->trans('flash.error.no_validate_rights'));

                return $this->redirectToRoute('match_submit_form', ['id' => $match->getId()]);
            }

            $this->resultSubmissionService->forceResult(
                $match,
                $user,
                $winnerId,
                $player1Score,
                $player2Score
            );

            $this->addFlash('success', $this->translator->trans('flash.success.match_result_validated'));

            return $this->redirectToRoute('round_show', [
                'id' => $match->getRound()->getTournament()->getId(),
                'roundNumber' => $match->getRound()->getRoundNumber(),
            ]);
        }

        // Standard player submission flow
        try {
            $this->resultSubmissionService->submitResult(
                $match,
                $user,
                $winnerId,
                $player1Score,
                $player2Score
            );

            // Check the result
            if ($match->isCompleted()) {
                $this->addFlash('success', $this->translator->trans('flash.success.match_result_confirmed'));

                return $this->redirectToRoute('round_show', [
                    'id' => $match->getRound()->getTournament()->getId(),
                    'roundNumber' => $match->getRound()->getRoundNumber(),
                ]);
            }

            if ($match->isDisputed()) {
                $this->addFlash('warning', $this->translator->trans('flash.warning.dispute_created'));

                return $this->redirectToRoute('round_show', [
                    'id' => $match->getRound()->getTournament()->getId(),
                    'roundNumber' => $match->getRound()->getRoundNumber(),
                ]);
            }

            // Still waiting for opponent
            return $this->redirectToRoute('match_submit_form', ['id' => $match->getId()]);
        } catch (InvalidSubmissionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('match_submit_form', ['id' => $match->getId()]);
        }
    }

    /**
     * Display a match result (for viewing after completion).
     */
    #[Route('/{id}', name: 'match_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(TournamentMatch $match): Response
    {
        return $this->render('match/show.html.twig', [
            'match' => $match,
        ]);
    }
}
