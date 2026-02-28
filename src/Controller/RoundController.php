<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Enum\BracketSize;
use App\Enum\PairingMode;
use App\Enum\TournamentStructure;
use App\Event\RoundStartedEvent;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Exception\PairingException;
use App\Exception\RoundNotCompleteException;
use App\Security\Voter\TournamentVoter;
use App\Service\BracketService;
use App\Service\GroupStageService;
use App\Service\PairingService;
use App\Service\TournamentCompletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for managing tournament rounds.
 *
 * Provides endpoints for:
 * - Starting the first round (tournament launch)
 * - Starting subsequent rounds
 * - Viewing round pairings
 */
#[Route('/tournaments/{id}/rounds', requirements: ['id' => '\d+'])]
final class RoundController extends AbstractController
{
    public function __construct(
        private readonly PairingService $pairingService,
        private readonly BracketService $bracketService,
        private readonly GroupStageService $groupStageService,
        private readonly TournamentCompletionService $completionService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Start the first round of a tournament.
     *
     * This action:
     * 1. Validates the organizer is authorized
     * 2. Generates Round 1 pairings
     * 3. Transitions tournament to ONGOING status
     *
     * FR32: Organizers manually start each round via button.
     */
    #[Route('/start-first', name: 'round_start_first', methods: ['POST'])]
    public function startFirstRound(
        Request $request,
        Tournament $tournament
    ): Response {
        // Security check - only organizer can start rounds
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('start-first-round-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToTournament($tournament);
        }

        // Get pairing mode from request (default to RANDOM)
        $modeValue = $request->request->getString('pairing_mode', 'random');
        $mode = PairingMode::tryFrom($modeValue) ?? PairingMode::RANDOM;

        try {
            // Check if this is a single elimination tournament
            if ($tournament->getStructure() === TournamentStructure::SINGLE_ELIMINATION) {
                // Use bracket service for elimination tournaments
                $playerCount = $tournament->getRegistrations()->count();
                $bracketSize = $this->bracketService->getRecommendedBracketSize($playerCount);

                // Start the tournament first (set status to ONGOING)
                $tournament->setStatus(\App\Enum\TournamentStatus::ONGOING);
                $tournament->setStartedAt(new \DateTimeImmutable());

                $round = $this->bracketService->generateBracketFirstRound($tournament, $bracketSize);
                $round->setIsEliminationRound(true);
            } elseif ($tournament->getStructure() === TournamentStructure::ROUND_ROBIN) {
                // Use round-robin pairing for championship format
                $round = $this->pairingService->generateRoundRobinPairings($tournament, 1);
            } else {
                // Use Swiss pairing for other tournament structures
                $round = $this->pairingService->generateRound1Pairings($tournament, $mode);
            }

            // Dispatch event for round start notifications (FR66)
            $this->eventDispatcher->dispatch(new RoundStartedEvent($round));

            $this->addFlash('success', sprintf(
                'Ronde 1 demarree! %d match(s) genere(s).',
                $round->getMatches()->count()
            ));
        } catch (InsufficientPlayersException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (InvalidTournamentStateException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToTournament($tournament);
    }

    /**
     * Start the next round of a tournament.
     *
     * This action:
     * 1. Validates the organizer is authorized
     * 2. Validates the previous round is complete
     * 3. Generates next round pairings using Swiss algorithm
     *
     * FR32: Organizers manually start each round via button.
     * FR30: System avoids rematches.
     * FR31: System pairs players with same points in priority.
     */
    #[Route('/start-next', name: 'round_start_next', methods: ['POST'])]
    public function startNextRound(
        Request $request,
        Tournament $tournament
    ): Response {
        // Security check - only organizer can start rounds
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('start-next-round-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToTournament($tournament);
        }

        try {
            // Check if this is a group stage tournament still in group phase
            $isGroupStagePhase = $tournament->getStructure() === TournamentStructure::GROUP_STAGE_ELIMINATION
                && $tournament->hasGroups()
                && !$tournament->isInEliminationPhase();

            if ($isGroupStagePhase) {
                // Use group stage service for round-robin pairings within groups
                $existingGroupRounds = $tournament->getRounds()->filter(
                    fn ($r) => !$r->isEliminationRound()
                )->count();
                $nextRoundNumber = $existingGroupRounds + 1;

                $round = $this->groupStageService->generateGroupRound($tournament, $nextRoundNumber);

                $this->eventDispatcher->dispatch(new RoundStartedEvent($round));

                $this->addFlash('success', sprintf(
                    'Ronde %d des groupes démarrée ! %d match(s) généré(s).',
                    $round->getRoundNumber(),
                    $round->getMatches()->count()
                ));

                return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
            }

            // Check if we're in elimination phase (for any tournament structure with elimination)
            $isEliminationPhase = $tournament->getStructure() === TournamentStructure::SINGLE_ELIMINATION
                || $tournament->isInEliminationPhase();

            if ($isEliminationPhase) {
                // Use bracket service for elimination rounds
                $previousRound = $tournament->getLatestRound();

                if ($previousRound === null) {
                    throw new InvalidTournamentStateException(
                        $tournament->getStatus(),
                        [\App\Enum\TournamentStatus::ONGOING],
                        'generer la prochaine ronde (aucune ronde precedente)'
                    );
                }

                // Check if bracket is complete (only 1 match in last round = final)
                if ($this->bracketService->isBracketComplete($tournament)) {
                    $this->addFlash('info', 'Le bracket est termine. Finalisez le tournoi.');

                    return $this->redirectToTournament($tournament);
                }

                // Check if we can advance (all matches completed, more than 1 winner)
                if (!$this->bracketService->canAdvanceBracket($previousRound)) {
                    $this->addFlash('error', 'La ronde precedente n\'est pas terminee ou il n\'y a plus assez de joueurs.');

                    return $this->redirectToTournament($tournament);
                }

                $round = $this->bracketService->generateNextBracketRound($tournament, $previousRound);
                $round->setIsEliminationRound(true);
            } elseif ($tournament->getStructure() === TournamentStructure::ROUND_ROBIN) {
                // Round-robin (championship) format
                $nextRoundNumber = $tournament->getRoundsCount() + 1;
                $round = $this->pairingService->generateRoundRobinPairings($tournament, $nextRoundNumber);
            } else {
                // Swiss phase - check if round limit has been reached
                if ($tournament->hasReachedSwissRoundLimit()) {
                    // For MIXED tournaments, prompt to start elimination phase
                    if ($tournament->getStructure() === TournamentStructure::MIXED) {
                        $this->addFlash('info', sprintf(
                            'Rondes suisses terminees (%d/%d). Demarrez la phase eliminatoire.',
                            $tournament->getRoundsCount(),
                            $tournament->getSwissRounds()
                        ));
                    } else {
                        $this->addFlash('error', sprintf(
                            'Limite de rondes atteinte (%d/%d). Terminez le tournoi ou modifiez la configuration.',
                            $tournament->getRoundsCount(),
                            $tournament->getSwissRounds()
                        ));
                    }

                    return $this->redirectToTournament($tournament);
                }

                // Use Swiss pairing for Swiss phase
                $round = $this->pairingService->generateSubsequentRoundPairings($tournament);
            }

            // Dispatch event for round start notifications (FR66)
            $this->eventDispatcher->dispatch(new RoundStartedEvent($round));

            $this->addFlash('success', sprintf(
                'Ronde %d demarree! %d match(s) genere(s).',
                $round->getRoundNumber(),
                $round->getMatches()->count()
            ));
        } catch (RoundNotCompleteException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (InvalidTournamentStateException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (PairingException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToTournament($tournament);
    }

    /**
     * View all rounds with their matches.
     */
    #[Route('', name: 'round_list', methods: ['GET'])]
    public function list(Tournament $tournament): Response
    {
        return $this->render('round/list.html.twig', [
            'tournament' => $tournament,
            'rounds' => $tournament->getRounds(),
            'current_round' => $tournament->getCurrentRound(),
        ]);
    }

    /**
     * View pairings for a specific round.
     */
    #[Route('/{roundNumber}', name: 'round_show', methods: ['GET'], requirements: ['roundNumber' => '\d+'])]
    public function show(Tournament $tournament, int $roundNumber): Response
    {
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

        // Check if current user is the organizer
        $user = $this->getUser();
        $isOrganizer = false;
        $userRegistrationId = null;

        if ($user !== null) {
            $organizer = $tournament->getOrganizer();
            $isOrganizer = $organizer !== null && $organizer->getId() === $user->getId();

            // Find user's registration if they're a player
            foreach ($tournament->getRegistrations() as $registration) {
                if ($registration->getPlayer()->getId() === $user->getId()) {
                    $userRegistrationId = $registration->getId();
                    break;
                }
            }
        }

        return $this->render('round/show.html.twig', [
            'tournament' => $tournament,
            'round' => $round,
            'matches' => $round->getMatches(),
            'is_organizer' => $isOrganizer,
            'user_registration_id' => $userRegistrationId,
        ]);
    }

    /**
     * Get the current standings for a tournament.
     */
    #[Route('/standings', name: 'round_standings', methods: ['GET'])]
    public function standings(Tournament $tournament): Response
    {
        // For group stage tournaments, redirect to the groups page which shows group standings
        if ($tournament->hasGroupStage() && !$tournament->isInEliminationPhase()) {
            return $this->redirectToRoute('tournament_groups', ['id' => $tournament->getId()]);
        }

        $standings = $this->pairingService->calculateStandings($tournament);

        // Sort by match points (desc), then OMWP (desc)
        $sortedStandings = array_values($standings);
        usort($sortedStandings, function ($a, $b): int {
            $pointsDiff = $b->getMatchPoints() <=> $a->getMatchPoints();
            if ($pointsDiff !== 0) {
                return $pointsDiff;
            }

            return $b->getOpponentMatchWinPercentage() <=> $a->getOpponentMatchWinPercentage();
        });

        // Check if user can manage (organizer) for drop actions
        $canManage = $this->isGranted(TournamentVoter::MANAGE, $tournament);

        return $this->render('round/standings.html.twig', [
            'tournament' => $tournament,
            'standings' => $sortedStandings,
            'can_manage' => $canManage,
        ]);
    }

    /**
     * Start elimination phase for MIXED tournaments.
     *
     * This action:
     * 1. Validates Swiss rounds are complete
     * 2. Generates elimination bracket from top players
     * 3. Marks tournament as in elimination phase
     */
    #[Route('/start-elimination', name: 'round_start_elimination', methods: ['POST'])]
    public function startEliminationPhase(
        Request $request,
        Tournament $tournament
    ): Response {
        // Security check - only organizer can start elimination phase
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('start-elimination-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToTournament($tournament);
        }

        // Validate this is a MIXED tournament
        if ($tournament->getStructure() !== TournamentStructure::MIXED) {
            $this->addFlash('error', 'Cette action n\'est disponible que pour les tournois en format mixte.');

            return $this->redirectToTournament($tournament);
        }

        // Validate we're not already in elimination phase
        if ($tournament->isInEliminationPhase()) {
            $this->addFlash('error', 'La phase eliminatoire a deja commence.');

            return $this->redirectToTournament($tournament);
        }

        // Validate Swiss rounds are complete
        $latestRound = $tournament->getLatestRound();
        if ($latestRound === null || !$latestRound->areAllMatchesCompleted()) {
            $this->addFlash('error', 'La ronde actuelle doit etre terminee avant de demarrer la phase eliminatoire.');

            return $this->redirectToTournament($tournament);
        }

        try {
            // Complete the last Swiss round if not already
            if (!$latestRound->isCompleted()) {
                $latestRound->complete();
            }

            // Get the elimination bracket size from tournament configuration
            $topCutSize = $tournament->getTopCutSize();
            if ($topCutSize !== null) {
                $bracketSize = BracketSize::from($topCutSize);
            } else {
                // Fallback to recommended size based on player count
                $playerCount = $tournament->getRegistrations()->count();
                $bracketSize = $this->bracketService->getRecommendedBracketSize($playerCount);
            }

            // Generate first elimination round
            $round = $this->bracketService->generateBracketFirstRound($tournament, $bracketSize);
            $round->setIsEliminationRound(true);

            // Dispatch event for round start notifications
            $this->eventDispatcher->dispatch(new RoundStartedEvent($round));

            $this->addFlash('success', sprintf(
                'Phase eliminatoire lancee! Top %d, %d match(s) genere(s).',
                $bracketSize->value,
                $round->getMatches()->count()
            ));
        } catch (InsufficientPlayersException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToTournament($tournament);
    }

    /**
     * Complete the tournament (FR54, FR56).
     *
     * This action:
     * 1. Validates all rounds are complete
     * 2. Changes tournament status to COMPLETED
     * 3. Auto-publishes results for PUBLIC tournaments
     */
    #[Route('/complete', name: 'round_complete_tournament', methods: ['POST'])]
    public function completeTournament(
        Request $request,
        Tournament $tournament
    ): Response {
        // Security check - only organizer can complete tournament
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('complete-tournament-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToTournament($tournament);
        }

        try {
            $this->completionService->completeSwissTournament($tournament);

            $this->addFlash('success', $this->translator->trans('flash.success.tournament_completed'));

            return $this->redirectToRoute('tournament_final_standings', ['id' => $tournament->getId()]);
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToTournament($tournament);
        }
    }

    /**
     * Start the round timer manually.
     *
     * This action allows organizers to start the timer independently
     * from the round start, giving them control over when time begins.
     */
    #[Route('/start-timer', name: 'round_start_timer', methods: ['POST'])]
    public function startTimer(
        Request $request,
        Tournament $tournament
    ): Response {
        // Security check - only organizer can start timer
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('start-timer-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToTournament($tournament);
        }

        $currentRound = $tournament->getCurrentRound();

        if ($currentRound === null) {
            $this->addFlash('error', 'Aucune ronde en cours.');

            return $this->redirectToTournament($tournament);
        }

        if ($currentRound->isTimerStarted()) {
            $this->addFlash('info', 'Le chronometre est deja lance.');

            return $this->redirectToTournament($tournament);
        }

        $currentRound->startTimer();
        $this->entityManager->flush();

        $this->addFlash('success', 'Chronometre lance!');

        return $this->redirectToTournament($tournament);
    }

    /**
     * Redirect to the tournament dashboard or show page.
     */
    private function redirectToTournament(Tournament $tournament): Response
    {
        // If tournament has rounds (ongoing), redirect to dashboard
        if ($tournament->hasRounds()) {
            return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
        }

        return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
    }
}
