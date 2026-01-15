<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Enum\PairingMode;
use App\Event\RoundStartedEvent;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Exception\PairingException;
use App\Exception\RoundNotCompleteException;
use App\Security\Voter\TournamentVoter;
use App\Service\PairingService;
use App\Service\TournamentCompletionService;
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
        private readonly TournamentCompletionService $completionService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
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
            $round = $this->pairingService->generateRound1Pairings($tournament, $mode);

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

        // Check if round limit has been reached
        if ($tournament->hasReachedSwissRoundLimit()) {
            $this->addFlash('error', sprintf(
                'Limite de rondes atteinte (%d/%d). Terminez le tournoi ou modifiez la configuration.',
                $tournament->getRoundsCount(),
                $tournament->getSwissRounds()
            ));

            return $this->redirectToTournament($tournament);
        }

        try {
            $round = $this->pairingService->generateSubsequentRoundPairings($tournament);

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

        return $this->render('round/standings.html.twig', [
            'tournament' => $tournament,
            'standings' => $sortedStandings,
        ]);
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
