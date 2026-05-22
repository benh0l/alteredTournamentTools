<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Exception\DropException;
use App\Repository\RegistrationRepository;
use App\Security\Voter\TournamentVoter;
use App\Service\DropService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for player drop functionality.
 *
 * Handles both player self-drop and organizer-initiated drops.
 */
#[Route('/tournaments/{id}', requirements: ['id' => '\d+'])]
final class DropController extends AbstractController
{
    public function __construct(
        private readonly DropService $dropService,
        private readonly RegistrationRepository $registrationRepository,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Player self-drop from tournament.
     * Endpoint: POST /tournaments/{id}/drop
     */
    #[Route('/drop', name: 'tournament_drop', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function drop(Request $request, Tournament $tournament): Response
    {
        // CSRF protection
        $submittedToken = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('drop-' . $tournament->getId(), $submittedToken)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        /** @var User $user */
        $user = $this->getUser();

        // Find player's registration
        $registration = $this->registrationRepository->findOneByTournamentAndPlayer($tournament, $user);
        if ($registration === null) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(
                    ['error' => 'Vous n\'etes pas inscrit a ce tournoi.'],
                    Response::HTTP_NOT_FOUND
                );
            }
            $this->addFlash('error', 'Vous n\'etes pas inscrit a ce tournoi.');

            return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
        }

        try {
            // Check if there's an active match and forfeit was requested
            $forfeitMatch = $request->request->getBoolean('forfeit_match');

            $this->dropService->dropPlayer(
                $registration,
                $forfeitMatch,
                '', // No reason for self-drop
                false // Not by organizer
            );

            $this->logger->info('Player dropped from tournament', [
                'player_id' => $user->getId(),
                'tournament_id' => $tournament->getId(),
            ]);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Vous avez abandonne le tournoi.',
                ]);
            }

            $this->addFlash('success', 'Vous avez abandonne le tournoi.');
        } catch (DropException $e) {
            $this->logger->warning('Drop failed', [
                'player_id' => $user->getId(),
                'tournament_id' => $tournament->getId(),
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'error' => $e->getMessage(),
                    'context' => $e->getContext(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
    }

    /**
     * Organizer drops a player from tournament.
     * Endpoint: POST /tournaments/{id}/registrations/{registrationId}/drop
     */
    #[Route('/registrations/{registrationId}/drop', name: 'tournament_organizer_drop', methods: ['POST'], requirements: ['registrationId' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function organizerDrop(Request $request, Tournament $tournament, int $registrationId): Response
    {
        // Check organizer permission
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        // CSRF protection
        $submittedToken = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('organizer-drop-' . $registrationId, $submittedToken)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Find registration
        $registration = $this->registrationRepository->find($registrationId);
        if ($registration === null || $registration->getTournament() !== $tournament) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(
                    ['error' => 'Inscription non trouvee.'],
                    Response::HTTP_NOT_FOUND
                );
            }
            throw $this->createNotFoundException('Inscription non trouvee.');
        }

        try {
            $forfeitMatch = $request->request->getBoolean('forfeit_match', true);
            $reason = $request->request->getString('reason', '');

            $this->dropService->dropPlayer(
                $registration,
                $forfeitMatch,
                $reason,
                true // By organizer
            );

            $playerName = $registration->getPlayer()->getDisplayName();

            $this->logger->info('Organizer dropped player from tournament', [
                'organizer_id' => $this->getUser()?->getId(),
                'player_id' => $registration->getPlayer()->getId(),
                'tournament_id' => $tournament->getId(),
                'reason' => $reason,
            ]);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => sprintf('Le joueur %s a ete retire du tournoi.', $playerName),
                ]);
            }

            $this->addFlash('success', sprintf('Le joueur %s a ete retire du tournoi.', $playerName));
        } catch (DropException $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'error' => $e->getMessage(),
                    'context' => $e->getContext(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
    }

    /**
     * Organizer reinstates a dropped player.
     * Endpoint: POST /tournaments/{id}/registrations/{registrationId}/undrop
     */
    #[Route('/registrations/{registrationId}/undrop', name: 'tournament_organizer_undrop', methods: ['POST'], requirements: ['registrationId' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function organizerUndrop(Request $request, Tournament $tournament, int $registrationId): Response
    {
        // Check organizer permission
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        // CSRF protection
        $submittedToken = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('organizer-undrop-' . $registrationId, $submittedToken)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Find registration
        $registration = $this->registrationRepository->find($registrationId);
        if ($registration === null || $registration->getTournament() !== $tournament) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(
                    ['error' => 'Inscription non trouvee.'],
                    Response::HTTP_NOT_FOUND
                );
            }
            throw $this->createNotFoundException('Inscription non trouvee.');
        }

        try {
            $this->dropService->undropPlayer($registration);

            $playerName = $registration->getPlayer()->getDisplayName();

            $this->logger->info('Organizer undropped player from tournament', [
                'organizer_id' => $this->getUser()?->getId(),
                'player_id' => $registration->getPlayer()->getId(),
                'tournament_id' => $tournament->getId(),
            ]);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => sprintf('Le joueur %s a ete reinstalle dans le tournoi.', $playerName),
                ]);
            }

            $this->addFlash('success', sprintf('Le joueur %s a ete reinstalle dans le tournoi.', $playerName));
        } catch (DropException $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'error' => $e->getMessage(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_dashboard', ['id' => $tournament->getId()]);
    }

    /**
     * Check if player has an active match (for UI feedback before drop).
     * Endpoint: GET /tournaments/{id}/drop/check
     */
    #[Route('/drop/check', name: 'tournament_drop_check', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkDropStatus(Tournament $tournament): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Find player's registration
        $registration = $this->registrationRepository->findOneByTournamentAndPlayer($tournament, $user);
        if ($registration === null) {
            return new JsonResponse([
                'canDrop' => false,
                'error' => 'Vous n\'etes pas inscrit a ce tournoi.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($registration->isDropped()) {
            return new JsonResponse([
                'canDrop' => false,
                'isDropped' => true,
                'droppedAt' => $registration->getDroppedAt()?->format('Y-m-d H:i:s'),
            ]);
        }

        $activeMatch = $this->dropService->getActiveMatch($registration);

        return new JsonResponse([
            'canDrop' => true,
            'hasActiveMatch' => $activeMatch !== null,
            'activeMatchId' => $activeMatch?->getId(),
            'activeMatchTableNumber' => $activeMatch?->getTableNumber(),
        ]);
    }
}
