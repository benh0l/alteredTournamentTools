<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Repository\TournamentGroupRepository;
use App\Repository\TournamentMatchRepository;
use App\Security\Voter\TournamentVoter;
use App\Service\GroupStageService;
use App\Service\GroupTransitionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for managing group stage tournaments.
 *
 * Provides endpoints for:
 * - Viewing group standings
 * - Forming groups
 * - Generating group round pairings
 * - Transitioning to elimination phase
 */
#[Route('/tournaments/{id}/groups', requirements: ['id' => '\d+'])]
final class GroupStageController extends AbstractController
{
    public function __construct(
        private readonly GroupStageService $groupStageService,
        private readonly GroupTransitionService $groupTransitionService,
        private readonly TournamentGroupRepository $groupRepository,
        private readonly TournamentMatchRepository $matchRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * View group standings and matches.
     */
    #[Route('', name: 'tournament_groups', methods: ['GET'])]
    public function index(Tournament $tournament): Response
    {
        if (!$tournament->hasGroupStage()) {
            $this->addFlash('error', $this->translator->trans('flash.error.groups_not_supported'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        $groups = $this->groupRepository->findByTournamentWithPlayers($tournament);
        $groupsFormed = count($groups) > 0;

        // Calculate standings for each group
        $standings = [];
        $groupMatches = [];
        $completedMatches = 0;
        $totalMatches = 0;

        foreach ($groups as $group) {
            $standings[$group->getLetter()] = $this->groupStageService->calculateGroupStandings($group);
            $matches = $this->matchRepository->findByGroup($group);
            $groupMatches[$group->getLetter()] = $matches;

            foreach ($matches as $match) {
                $totalMatches++;
                if ($match->isCompleted()) {
                    $completedMatches++;
                }
            }
        }

        $allMatchesComplete = $groupsFormed && $totalMatches > 0 && $completedMatches === $totalMatches;

        // Check if group phase is complete (all round-robin rounds played)
        $groupPhaseComplete = false;
        if ($groupsFormed && $allMatchesComplete) {
            // Get the number of group rounds played
            $groupRoundsPlayed = $tournament->getRounds()->filter(
                fn ($r) => !$r->isEliminationRound()
            )->count();

            // Get the required rounds (use first group as reference - all groups should have same player count)
            $requiredRounds = $groups[0]->getRequiredRounds();

            $groupPhaseComplete = $groupRoundsPlayed >= $requiredRounds;
        }

        return $this->render('tournament/group_standings.html.twig', [
            'tournament' => $tournament,
            'groups' => $groups,
            'standings' => $standings,
            'groupMatches' => $groupMatches,
            'groupsFormed' => $groupsFormed,
            'completedMatches' => $completedMatches,
            'totalMatches' => $totalMatches,
            'allMatchesComplete' => $allMatchesComplete,
            'groupPhaseComplete' => $groupPhaseComplete,
            'isInEliminationPhase' => $tournament->isInEliminationPhase(),
        ]);
    }

    /**
     * Form groups from registered players.
     */
    #[Route('/form', name: 'tournament_form_groups', methods: ['POST'])]
    public function formGroups(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        if (!$this->isCsrfTokenValid('form-groups-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
        }

        if (!$tournament->hasGroupStage()) {
            $this->addFlash('error', $this->translator->trans('flash.error.groups_not_supported'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        // Check if groups already exist
        if ($tournament->hasGroups()) {
            $this->addFlash('error', $this->translator->trans('flash.error.groups_already_formed'));

            return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
        }

        try {
            $groups = $this->groupStageService->formGroups($tournament);

            $this->addFlash('success', sprintf(
                '%d groupes formés avec succès !',
                count($groups)
            ));
        } catch (InsufficientPlayersException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (InvalidTournamentStateException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
    }

    /**
     * Generate the next round of group matches.
     */
    #[Route('/next-round', name: 'tournament_next_group_round', methods: ['POST'])]
    public function nextGroupRound(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        if (!$this->isCsrfTokenValid('next-group-round-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
        }

        if (!$tournament->hasGroupStage()) {
            $this->addFlash('error', $this->translator->trans('flash.error.groups_not_supported'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        if (!$tournament->hasGroups()) {
            $this->addFlash('error', $this->translator->trans('flash.error.groups_not_formed'));

            return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
        }

        try {
            // Calculate next round number (count existing group rounds + 1)
            $existingGroupRounds = $tournament->getRounds()->filter(
                fn ($r) => !$r->isEliminationRound()
            )->count();
            $nextRoundNumber = $existingGroupRounds + 1;

            $round = $this->groupStageService->generateGroupRound($tournament, $nextRoundNumber);

            $this->addFlash('success', sprintf(
                'Ronde %d des groupes démarrée ! %d match(s) généré(s).',
                $round->getRoundNumber(),
                $round->getMatches()->count()
            ));

            return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
        } catch (InvalidTournamentStateException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
    }

    /**
     * Transition from group stage to elimination bracket.
     */
    #[Route('/start-elimination', name: 'tournament_start_elimination', methods: ['POST'])]
    public function startElimination(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        if (!$this->isCsrfTokenValid('start-elimination-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
        }

        if (!$tournament->hasGroupStage()) {
            $this->addFlash('error', $this->translator->trans('flash.error.groups_not_supported'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        try {
            $round = $this->groupTransitionService->transitionToElimination($tournament);

            $qualifiers = $tournament->getTotalQualifiers() ?? 0;

            $this->addFlash('success', sprintf(
                'Phase eliminatoire lancee! %d joueurs qualifies, %d match(s) genere(s).',
                $qualifiers,
                $round->getMatches()->count()
            ));

            return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
        } catch (InvalidTournamentStateException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
    }
}
