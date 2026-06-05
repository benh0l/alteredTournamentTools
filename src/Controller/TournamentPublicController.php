<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for public tournament views (FR62).
 *
 * Provides public-facing tournament information for discovery and registration.
 * Only PUBLIC visibility tournaments are accessible to anonymous users.
 */
#[Route('/tournaments')]
final class TournamentPublicController extends AbstractController
{
    public function __construct(
        private readonly RegistrationRepository $registrationRepository,
        private readonly TournamentRepository $tournamentRepository,
    ) {
    }

    /**
     * View tournament public details (FR62).
     *
     * Shows complete tournament information before registration:
     * - Date, time, location
     * - Format, structure
     * - Entry fee, prizes
     * - Organizer info
     * - Registration status and button
     */
    #[Route('/{id}/details', name: 'tournament_public_details', methods: ['GET'])]
    public function details(Tournament $tournament): Response
    {
        // Only PUBLIC tournaments are viewable by anonymous users
        if ($tournament->getVisibility() !== TournamentVisibility::PUBLIC) {
            throw $this->createNotFoundException('Tournoi non trouve.');
        }

        // Check if current user is already registered
        $isRegistered = false;
        $user = $this->getUser();
        if ($user !== null) {
            $registration = $this->registrationRepository->findOneBy([
                'player' => $user,
                'tournament' => $tournament,
            ]);
            $isRegistered = $registration !== null;
        }

        // Check registration availability
        $canRegister = $tournament->acceptsRegistrations()
            && $tournament->hasAvailableSpots()
            && !$isRegistered;

        return $this->render('tournament/public_details.html.twig', [
            'tournament' => $tournament,
            'is_registered' => $isRegistered,
            'can_register' => $canRegister,
            'user' => $user,
        ]);
    }

    #[Route('/series/{groupId}', name: 'tournament_series', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function series(string $groupId): Response
    {
        $tournaments = $this->tournamentRepository->findByRecurrenceGroup($groupId);

        if (empty($tournaments)) {
            throw $this->createNotFoundException('Série introuvable.');
        }

        $user = $this->getUser();
        $firstTournament = $tournaments[0];

        if ($firstTournament->getOrganizer() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('tournament/series.html.twig', [
            'tournaments' => $tournaments,
            'groupId' => $groupId,
        ]);
    }
}
