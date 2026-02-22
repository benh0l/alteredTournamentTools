<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\Faction;
use App\Exception\AlreadyRegisteredException;
use App\Exception\TournamentNotOpenException;
use App\Message\SendRegistrationRemovalMessage;
use App\Repository\RegistrationRepository;
use App\Repository\UserRepository;
use App\Security\Voter\TournamentVoter;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/tournaments')]
final class TournamentRegistrationsController extends AbstractController
{
    public function __construct(
        private readonly RegistrationRepository $registrationRepository,
        private readonly RegistrationService $registrationService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly ?MessageBusInterface $messageBus = null,
    ) {
    }

    #[Route('/{id}/registrations', name: 'tournament_registrations', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function index(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        $sortField = $request->query->getString('sort', 'registeredAt');
        $sortDirection = $request->query->getString('direction', 'ASC');

        // Validate sort parameters
        $allowedSorts = ['registeredAt', 'player.pseudo'];
        if (!in_array($sortField, $allowedSorts, true)) {
            $sortField = 'registeredAt';
        }
        $sortDirection = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

        $registrations = $this->registrationRepository->findByTournamentWithPlayer(
            $tournament,
            $sortField,
            $sortDirection
        );

        return $this->render('tournament/registrations.html.twig', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'sort_field' => $sortField,
            'sort_direction' => $sortDirection,
            'heroesData' => Faction::getHeroesGroupedByFaction($tournament->getFormat()),
            'factionChoices' => Faction::getChoices(),
        ]);
    }

    #[Route('/{id}/registrations/export', name: 'tournament_registrations_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function export(Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        $registrations = $this->registrationRepository->findByTournamentWithPlayer($tournament);

        $csv = "Pseudo,Nom,Email,Decklist,Date d'inscription\n";
        foreach ($registrations as $registration) {
            $player = $registration->getPlayer();
            $csv .= sprintf(
                "%s,%s,%s,%s,%s\n",
                $this->escapeCsv($player->getPseudo()),
                $this->escapeCsv($player->getName() ?? ''),
                $this->escapeCsv($player->getEmail()),
                $this->escapeCsv($registration->getDecklistUrl() ?? ''),
                $registration->getRegisteredAt()->format('Y-m-d H:i:s')
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set(
            'Content-Disposition',
            sprintf(
                'attachment; filename="inscriptions_%s_%s.csv"',
                $tournament->getId(),
                date('Y-m-d')
            )
        );

        return $response;
    }

    #[Route('/{id}/registrations/close', name: 'tournament_close_registrations', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function closeRegistrations(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        if (!$this->isCsrfTokenValid('close_registrations' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $tournament->setRegistrationsClosed(true);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.success.registrations_closed'));
        $this->logger->info('Tournament registrations closed', [
            'tournament_id' => $tournament->getId(),
        ]);

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/registrations/reopen', name: 'tournament_reopen_registrations', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reopenRegistrations(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        if (!$this->isCsrfTokenValid('reopen_registrations' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $tournament->setRegistrationsClosed(false);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.success.registrations_reopened'));
        $this->logger->info('Tournament registrations reopened', [
            'tournament_id' => $tournament->getId(),
        ]);

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/registrations/{playerId}/remove', name: 'tournament_remove_player', methods: ['POST'], requirements: ['id' => '\d+', 'playerId' => '\d+'])]
    public function removePlayer(
        Request $request,
        Tournament $tournament,
        int $playerId
    ): Response {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        if (!$this->isCsrfTokenValid('remove_player' . $playerId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $registration = $this->registrationRepository->findOneBy([
            'tournament' => $tournament,
            'player' => $playerId,
        ]);

        if ($registration === null) {
            $this->addFlash('error', $this->translator->trans('flash.error.player_not_registered'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        $player = $registration->getPlayer();
        $playerPseudo = $player->getPseudo();
        $playerEmail = $player->getEmail();

        try {
            $this->registrationService->unregisterPlayer($player, $tournament);
            $this->addFlash('success', sprintf('%s a ete retire du tournoi.', $playerPseudo));
            $this->logger->info('Player removed from tournament by organizer', [
                'player_id' => $playerId,
                'player_pseudo' => $playerPseudo,
                'tournament_id' => $tournament->getId(),
            ]);

            // Send removal notification email (async)
            if ($this->messageBus !== null) {
                $this->messageBus->dispatch(new SendRegistrationRemovalMessage(
                    $playerEmail,
                    $playerPseudo,
                    $tournament->getName(),
                    $tournament->getId() ?? 0
                ));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/registrations/search-users', name: 'tournament_registrations_search_users', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function searchUsers(Request $request, Tournament $tournament): JsonResponse
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        $query = $request->query->getString('q', '');

        if (strlen($query) < 2) {
            return new JsonResponse(['users' => []]);
        }

        // Search users by email or pseudo
        $result = $this->userRepository->searchUsers($query, null, 1, 10);
        $users = $result['users'];

        // Filter out already registered users
        $registeredPlayerIds = array_map(
            fn ($reg) => $reg->getPlayer()->getId(),
            $this->registrationRepository->findByTournament($tournament)
        );

        $availableUsers = array_filter(
            $users,
            fn (User $user) => !in_array($user->getId(), $registeredPlayerIds, true)
        );

        $data = array_map(fn (User $user) => [
            'id' => $user->getId(),
            'pseudo' => $user->getPseudo(),
            'email' => $user->getEmail(),
        ], array_values($availableUsers));

        return new JsonResponse(['users' => $data]);
    }

    #[Route('/{id}/registrations/add', name: 'tournament_registrations_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addPlayer(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        if (!$this->isCsrfTokenValid('add_player' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $userId = $request->request->getInt('user_id');
        $decklistUrl = $request->request->getString('decklist_url', '');
        $factionValue = $request->request->getString('faction', '');
        $faction2Value = $request->request->getString('faction2', '');
        $hero = $request->request->getString('hero', '');

        if ($userId === 0) {
            $this->addFlash('error', $this->translator->trans('flash.error.select_player'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        $player = $this->userRepository->find($userId);

        if ($player === null) {
            $this->addFlash('error', $this->translator->trans('flash.error.player_not_found'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        // Convert faction strings to Faction enum
        $faction = $factionValue !== '' ? Faction::tryFrom($factionValue) : null;
        $faction2 = $faction2Value !== '' ? Faction::tryFrom($faction2Value) : null;

        try {
            $this->registrationService->registerPlayerByOrganizer(
                $player,
                $tournament,
                $decklistUrl ?: null,
                $faction,
                $hero ?: null,
                $faction2
            );

            $this->addFlash('success', sprintf('%s a ete ajoute au tournoi.', $player->getPseudo()));
            $this->logger->info('Player added to tournament by organizer', [
                'player_id' => $player->getId(),
                'player_pseudo' => $player->getPseudo(),
                'tournament_id' => $tournament->getId(),
                'organizer_id' => $this->getUser()->getId(),
            ]);
        } catch (AlreadyRegisteredException) {
            $this->addFlash('error', $this->translator->trans('flash.error.player_already_registered'));
        } catch (TournamentNotOpenException) {
            $this->addFlash('error', $this->translator->trans('flash.error.registrations_not_accepted'));
        }

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/registrations/add-guest', name: 'tournament_registrations_add_guest', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addGuestPlayer(Request $request, Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        if (!$this->isCsrfTokenValid('add_guest' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $guestName = trim($request->request->getString('guest_name', ''));
        $decklistUrl = $request->request->getString('decklist_url', '');
        $factionValue = $request->request->getString('faction', '');
        $faction2Value = $request->request->getString('faction2', '');
        $hero = $request->request->getString('hero', '');

        if ($guestName === '') {
            $this->addFlash('error', $this->translator->trans('flash.error.guest_name_required'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        // Create guest user with unique pseudo and fake email
        $uuid = Uuid::v4()->toRfc4122();
        $guestPseudo = 'guest_' . substr($uuid, 0, 8);
        $guestEmail = 'guest-' . $uuid . '@tournament.local';

        $guestUser = new User();
        $guestUser->setEmail($guestEmail);
        $guestUser->setPseudo($guestPseudo);
        $guestUser->setName($guestName);
        $guestUser->setPassword(''); // No password for guest users
        $guestUser->setIsGuest(true);
        $guestUser->setIsVerified(false);

        $this->entityManager->persist($guestUser);
        $this->entityManager->flush(); // Flush to get the user ID before registration

        // Convert faction strings to Faction enum
        $faction = $factionValue !== '' ? Faction::tryFrom($factionValue) : null;
        $faction2 = $faction2Value !== '' ? Faction::tryFrom($faction2Value) : null;

        try {
            $this->registrationService->registerPlayerByOrganizer(
                $guestUser,
                $tournament,
                $decklistUrl ?: null,
                $faction,
                $hero ?: null,
                $faction2
            );

            $this->addFlash('success', sprintf($this->translator->trans('flash.success.guest_added'), $guestName));
            $this->logger->info('Guest player added to tournament', [
                'guest_name' => $guestName,
                'guest_user_id' => $guestUser->getId(),
                'tournament_id' => $tournament->getId(),
                'organizer_id' => $this->getUser()->getId(),
            ]);
        } catch (AlreadyRegisteredException) {
            $this->addFlash('error', $this->translator->trans('flash.error.player_already_registered'));
        } catch (TournamentNotOpenException) {
            $this->addFlash('error', $this->translator->trans('flash.error.registrations_not_accepted'));
        }

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/registrations/{registrationId}/edit', name: 'organizer_registration_edit', methods: ['POST'], requirements: ['id' => '\d+', 'registrationId' => '\d+'])]
    public function editRegistration(
        Request $request,
        Tournament $tournament,
        int $registrationId
    ): Response {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        if (!$this->isCsrfTokenValid('edit_registration' . $registrationId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $registration = $this->registrationRepository->find($registrationId);

        if ($registration === null || $registration->getTournament()->getId() !== $tournament->getId()) {
            $this->addFlash('error', $this->translator->trans('flash.error.registration_not_found'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        $factionValue = $request->request->getString('faction', '');
        $faction2Value = $request->request->getString('faction2', '');
        $hero = $request->request->getString('hero', '');
        $decklistUrl = $request->request->getString('decklist_url', '');

        // Update registration fields
        $registration->setFaction($factionValue !== '' ? Faction::tryFrom($factionValue) : null);
        $registration->setFaction2($faction2Value !== '' ? Faction::tryFrom($faction2Value) : null);
        $registration->setHero($hero !== '' ? $hero : null);
        $registration->setDecklistUrl($decklistUrl !== '' ? $decklistUrl : null);

        $this->entityManager->flush();

        $this->addFlash('success', sprintf(
            $this->translator->trans('flash.success.player_registration_updated'),
            $registration->getPlayer()->getPseudo()
        ));

        $this->logger->info('Registration updated by organizer', [
            'registration_id' => $registrationId,
            'player_pseudo' => $registration->getPlayer()->getPseudo(),
            'tournament_id' => $tournament->getId(),
            'organizer_id' => $this->getUser()->getId(),
        ]);

        return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/registrations/{registrationId}/data', name: 'tournament_registration_data', methods: ['GET'], requirements: ['id' => '\d+', 'registrationId' => '\d+'])]
    public function getRegistrationData(Tournament $tournament, int $registrationId): JsonResponse
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        $registration = $this->registrationRepository->find($registrationId);

        if ($registration === null || $registration->getTournament()->getId() !== $tournament->getId()) {
            return new JsonResponse(['error' => 'Registration not found'], 404);
        }

        return new JsonResponse([
            'id' => $registration->getId(),
            'playerPseudo' => $registration->getPlayer()->getPseudo(),
            'faction' => $registration->getFaction()?->value,
            'faction2' => $registration->getFaction2()?->value,
            'hero' => $registration->getHero(),
            'decklistUrl' => $registration->getDecklistUrl(),
        ]);
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
