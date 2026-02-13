<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\User;
use App\Repository\RegistrationRepository;
use App\Security\Voter\TournamentVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for tournament check-in functionality.
 *
 * Handles player self-check-in and organizer check-in management.
 */
#[Route('/tournaments/{id}', requirements: ['id' => '\d+'])]
final class CheckInController extends AbstractController
{
    public function __construct(
        private readonly RegistrationRepository $registrationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Player self-check-in to tournament.
     * Endpoint: POST /tournaments/{id}/check-in
     */
    #[Route('/check-in', name: 'tournament_checkin', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkIn(Request $request, Tournament $tournament): Response
    {
        // CSRF protection
        $submittedToken = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('checkin-' . $tournament->getId(), $submittedToken)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Check if check-in is enabled and open
        if (!$tournament->canCheckIn()) {
            $message = $this->translator->trans('flash.error.checkin_not_available');
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', $message);

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Find player's registration
        $registration = $this->registrationRepository->findOneByTournamentAndPlayer($tournament, $user);
        if ($registration === null) {
            $message = $this->translator->trans('flash.error.not_registered');
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $message], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('error', $message);

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        // Check if already checked in
        if ($registration->isCheckedIn()) {
            $message = $this->translator->trans('flash.error.already_checked_in');
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('warning', $message);

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        // Check if dropped
        if ($registration->isDropped()) {
            $message = $this->translator->trans('flash.error.cannot_checkin_dropped');
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', $message);

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        // Perform check-in
        $registration->checkIn();
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $this->logger->info('Player checked in to tournament', [
            'player_id' => $user->getId(),
            'tournament_id' => $tournament->getId(),
        ]);

        $message = $this->translator->trans('flash.success.checkin_success');
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'checkedInAt' => $registration->getCheckedInAt()?->format('H:i'),
            ]);
        }

        $this->addFlash('success', $message);

        return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
    }

    /**
     * Open check-in for tournament (organizer only).
     * Endpoint: POST /tournaments/{id}/check-in/open
     */
    #[Route('/check-in/open', name: 'tournament_checkin_open', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function openCheckIn(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('checkin_open' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if (!$tournament->isCheckInEnabled()) {
            $this->addFlash('error', $this->translator->trans('flash.error.checkin_not_enabled'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        $tournament->openCheckIn();
        $this->entityManager->flush();

        $this->logger->info('Check-in opened for tournament', [
            'tournament_id' => $tournament->getId(),
            'organizer_id' => $this->getUser()?->getId(),
        ]);

        $this->addFlash('success', $this->translator->trans('flash.success.checkin_opened'));

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    /**
     * Close check-in for tournament (organizer only).
     * Endpoint: POST /tournaments/{id}/check-in/close
     */
    #[Route('/check-in/close', name: 'tournament_checkin_close', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function closeCheckIn(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('checkin_close' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $tournament->closeCheckIn();
        $this->entityManager->flush();

        $this->logger->info('Check-in closed for tournament', [
            'tournament_id' => $tournament->getId(),
            'organizer_id' => $this->getUser()?->getId(),
        ]);

        $this->addFlash('success', $this->translator->trans('flash.success.checkin_closed'));

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    /**
     * Organizer manually checks in a player.
     * Endpoint: POST /tournaments/{id}/registrations/{registrationId}/check-in
     */
    #[Route('/registrations/{registrationId}/check-in', name: 'tournament_organizer_checkin', methods: ['POST'], requirements: ['registrationId' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function organizerCheckIn(Request $request, Tournament $tournament, int $registrationId): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        // CSRF protection
        $submittedToken = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('organizer-checkin-' . $registrationId, $submittedToken)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Find registration
        $registration = $this->registrationRepository->find($registrationId);
        if ($registration === null || $registration->getTournament() !== $tournament) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Inscription non trouvee.'], Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Inscription non trouvee.');
        }

        if ($registration->isCheckedIn()) {
            $message = $this->translator->trans('flash.error.player_already_checked_in');
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('warning', $message);

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        $registration->checkIn();
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $playerName = $registration->getPlayer()->getDisplayName();

        $this->logger->info('Organizer checked in player', [
            'organizer_id' => $this->getUser()?->getId(),
            'player_id' => $registration->getPlayer()->getId(),
            'tournament_id' => $tournament->getId(),
        ]);

        $message = $this->translator->trans('flash.success.player_checked_in', ['%player%' => $playerName]);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'checkedInAt' => $registration->getCheckedInAt()?->format('H:i'),
            ]);
        }

        $this->addFlash('success', $message);

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    /**
     * Organizer undoes a player's check-in.
     * Endpoint: POST /tournaments/{id}/registrations/{registrationId}/check-in/undo
     */
    #[Route('/registrations/{registrationId}/check-in/undo', name: 'tournament_organizer_undo_checkin', methods: ['POST'], requirements: ['registrationId' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function organizerUndoCheckIn(Request $request, Tournament $tournament, int $registrationId): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        // CSRF protection
        $submittedToken = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('organizer-undo-checkin-' . $registrationId, $submittedToken)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Find registration
        $registration = $this->registrationRepository->find($registrationId);
        if ($registration === null || $registration->getTournament() !== $tournament) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Inscription non trouvee.'], Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Inscription non trouvee.');
        }

        if (!$registration->isCheckedIn()) {
            $message = $this->translator->trans('flash.error.player_not_checked_in');
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('warning', $message);

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        $registration->undoCheckIn();
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $playerName = $registration->getPlayer()->getDisplayName();

        $this->logger->info('Organizer undid player check-in', [
            'organizer_id' => $this->getUser()?->getId(),
            'player_id' => $registration->getPlayer()->getId(),
            'tournament_id' => $tournament->getId(),
        ]);

        $message = $this->translator->trans('flash.success.player_checkin_undone', ['%player%' => $playerName]);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $message,
            ]);
        }

        $this->addFlash('success', $message);

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    /**
     * Remove all players who haven't checked in (organizer only).
     * Endpoint: POST /tournaments/{id}/check-in/remove-absent
     */
    #[Route('/check-in/remove-absent', name: 'tournament_checkin_remove_absent', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeAbsentPlayers(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('checkin_remove_absent' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if (!$tournament->isCheckInEnabled()) {
            $this->addFlash('error', $this->translator->trans('flash.error.checkin_not_enabled'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        // Get all registrations that haven't checked in
        $notCheckedIn = $tournament->getNotCheckedInRegistrations();
        $count = $notCheckedIn->count();

        if ($count === 0) {
            $this->addFlash('info', $this->translator->trans('flash.info.no_absent_players'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        // Remove each registration
        foreach ($notCheckedIn as $registration) {
            $tournament->removeRegistration($registration);
            $this->entityManager->remove($registration);
        }

        $this->entityManager->flush();

        $this->logger->info('Removed absent players from tournament', [
            'tournament_id' => $tournament->getId(),
            'organizer_id' => $this->getUser()?->getId(),
            'removed_count' => $count,
        ]);

        $this->addFlash('success', $this->translator->trans('flash.success.absent_players_removed', ['%count%' => $count]));

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    /**
     * Get check-in status for current player (for UI feedback).
     * Endpoint: GET /tournaments/{id}/check-in/status
     */
    #[Route('/check-in/status', name: 'tournament_checkin_status', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkInStatus(Tournament $tournament): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Find player's registration
        $registration = $this->registrationRepository->findOneByTournamentAndPlayer($tournament, $user);
        if ($registration === null) {
            return new JsonResponse([
                'isRegistered' => false,
            ]);
        }

        return new JsonResponse([
            'isRegistered' => true,
            'isCheckedIn' => $registration->isCheckedIn(),
            'checkedInAt' => $registration->getCheckedInAt()?->format('H:i'),
            'checkInEnabled' => $tournament->isCheckInEnabled(),
            'checkInOpen' => $tournament->isCheckInOpen(),
            'canCheckIn' => $tournament->canCheckIn() && !$registration->isCheckedIn() && !$registration->isDropped(),
        ]);
    }
}
