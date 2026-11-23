<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\DeckChoice;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\Faction;
use App\Exception\AlreadyRegisteredException;
use App\Exception\InvalidTeamException;
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        private readonly UserPasswordHasherInterface $passwordHasher,
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

        $isTeam = $tournament->isTeamTournament();
        $csv = $isTeam
            ? "Pseudo,Nom,Email,Decklist,Pseudo partenaire,Nom partenaire,Email partenaire,Decklist partenaire,Date d'inscription\n"
            : "Pseudo,Nom,Email,Decklist,Date d'inscription\n";
        foreach ($registrations as $registration) {
            $player = $registration->getPlayer();
            $columns = [
                $this->escapeCsv($player->getPseudo()),
                $this->escapeCsv($player->getName() ?? ''),
                $this->escapeCsv($player->getEmail()),
                $this->escapeCsv($registration->getDecklistUrl() ?? ''),
            ];
            if ($isTeam) {
                $partner = $registration->getPartner();
                $columns[] = $this->escapeCsv($partner?->getPseudo() ?? '');
                $columns[] = $this->escapeCsv($partner?->getName() ?? '');
                $columns[] = $this->escapeCsv($partner?->getEmail() ?? '');
                $columns[] = $this->escapeCsv($registration->getPartnerDecklistUrl() ?? '');
            }
            $columns[] = $registration->getRegisteredAt()->format('Y-m-d H:i:s');
            $csv .= implode(',', $columns) . "\n";
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

    #[Route('/{id}/registrations/{registrationId}/remove', name: 'tournament_remove_player', methods: ['POST'], requirements: ['id' => '\d+', 'registrationId' => '\d+'])]
    public function removePlayer(
        Request $request,
        Tournament $tournament,
        int $registrationId
    ): Response {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        if (!$this->isCsrfTokenValid('remove_registration' . $registrationId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $registration = $this->registrationRepository->find($registrationId);

        if ($registration === null || $registration->getTournament()->getId() !== $tournament->getId()) {
            $this->addFlash('error', $this->translator->trans('flash.error.player_not_registered'));

            return $this->redirectToRoute('tournament_registrations', ['id' => $tournament->getId()]);
        }

        $members = $registration->getMembers();
        $displayName = $registration->getDisplayName();

        try {
            $this->registrationService->unregisterPlayer($registration->getPlayer(), $tournament);
            $this->addFlash('success', sprintf('%s a ete retire du tournoi.', $displayName));
            $this->logger->info('Registration removed from tournament by organizer', [
                'registration_id' => $registrationId,
                'player_ids' => array_map(static fn (User $member) => $member->getId(), $members),
                'tournament_id' => $tournament->getId(),
            ]);

            // Send removal notification email to every member (async)
            if ($this->messageBus !== null) {
                foreach ($members as $member) {
                    $this->messageBus->dispatch(new SendRegistrationRemovalMessage(
                        $member->getEmail(),
                        $member->getPseudo(),
                        $tournament->getName(),
                        $tournament->getId() ?? 0
                    ));
                }
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
        $registeredPlayerIds = [];
        foreach ($this->registrationRepository->findByTournament($tournament) as $registration) {
            foreach ($registration->getMembers() as $member) {
                $registeredPlayerIds[] = $member->getId();
            }
        }

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
            $registration = $this->registrationService->registerPlayerByOrganizer(
                $player,
                $tournament,
                $decklistUrl ?: null,
                $faction,
                $hero ?: null,
                $faction2,
                false,
                $this->resolvePartner($request, $tournament),
                $this->partnerDeckFromRequest($request)
            );

            $this->addFlash('success', sprintf('%s a ete ajoute au tournoi.', $registration->getDisplayName()));
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
        } catch (InvalidTeamException $e) {
            $this->addFlash('error', $this->translator->trans($e->getTranslationKey()));
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

        $guestUser = $this->createGuestUser($guestName);

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
                $faction2,
                false,
                $this->resolvePartner($request, $tournament),
                $this->partnerDeckFromRequest($request)
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
        } catch (InvalidTeamException $e) {
            $this->addFlash('error', $this->translator->trans($e->getTranslationKey()));
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
            $registration->getPseudo()
        ));

        $this->logger->info('Registration updated by organizer', [
            'registration_id' => $registrationId,
            'player_pseudo' => $registration->getPseudo(),
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
            'playerPseudo' => $registration->getPseudo(),
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

    /**
     * Create a guest account (no login possible) for a player without an account.
     */
    private function createGuestUser(string $name): User
    {
        $uuid = Uuid::v4()->toRfc4122();

        $guestUser = new User();
        $guestUser->setEmail('guest-' . $uuid . '@tournament.local');
        $guestUser->setPseudo('guest_' . substr($uuid, 0, 8));
        $guestUser->setName($name);
        // Set unusable password (random UUID hashed) - prevents login while maintaining security
        $guestUser->setPassword($this->passwordHasher->hashPassword($guestUser, Uuid::v4()->toRfc4122()));
        $guestUser->setIsGuest(true);
        $guestUser->setIsVerified(false);

        $this->entityManager->persist($guestUser);
        $this->entityManager->flush(); // Flush to get the user ID before registration

        return $guestUser;
    }

    /**
     * Partner of a team being added by the organizer: an existing account (partner_pseudo)
     * or a new guest (partner_guest_name). Null for 1v1 tournaments.
     *
     * @throws InvalidTeamException
     */
    private function resolvePartner(Request $request, Tournament $tournament): ?User
    {
        if (!$tournament->isTeamTournament()) {
            return null;
        }

        $pseudo = trim($request->request->getString('partner_pseudo', ''));
        if ($pseudo !== '') {
            return $this->userRepository->findByPseudo($pseudo)
                ?? throw InvalidTeamException::because(InvalidTeamException::PARTNER_NOT_FOUND);
        }

        $guestName = trim($request->request->getString('partner_guest_name', ''));
        if ($guestName !== '') {
            return $this->createGuestUser($guestName);
        }

        throw InvalidTeamException::because(InvalidTeamException::PARTNER_REQUIRED);
    }

    private function partnerDeckFromRequest(Request $request): DeckChoice
    {
        return DeckChoice::fromRaw(
            $request->request->getString('partner_decklist_url', ''),
            $request->request->getString('partner_faction', ''),
            $request->request->getString('partner_faction2', ''),
            $request->request->getString('partner_hero', ''),
        );
    }
}
