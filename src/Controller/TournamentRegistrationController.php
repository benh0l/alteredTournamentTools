<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\Faction;
use App\Enum\TournamentStatus;
use App\Exception\AlreadyRegisteredException;
use App\Form\RegistrationType;
use App\Repository\RegistrationRepository;
use App\Service\RegistrationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/tournaments')]
final class TournamentRegistrationController extends AbstractController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly RegistrationRepository $registrationRepository,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/{id}/register', name: 'tournament_register', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function register(Request $request, Tournament $tournament): Response
    {
        // Check tournament is open for registration
        if ($tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            throw $this->createAccessDeniedException(
                'Les inscriptions ne sont pas ouvertes pour ce tournoi.'
            );
        }

        // Check if registrations are closed
        if ($tournament->isRegistrationsClosed()) {
            $this->addFlash('error', $this->translator->trans('flash.error.registrations_closed'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Check if already registered
        if ($this->registrationService->isPlayerRegistered($user, $tournament)) {
            $this->addFlash('warning', $this->translator->trans('flash.error.already_registered'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        $form = $this->createForm(RegistrationType::class, null, [
            'tournament_format' => $tournament->getFormat(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Get faction from string value
                $factionValue = $form->get('faction')->getData();
                $faction = $factionValue ? Faction::tryFrom($factionValue) : null;

                $this->registrationService->registerPlayerToTournament(
                    $user,
                    $tournament,
                    $form->get('decklistUrl')->getData(),
                    $faction,
                    $form->get('hero')->getData()
                );

                $this->addFlash('success', $this->translator->trans('flash.success.registration_success'));
                $this->logger->info('Player registered to tournament', [
                    'player_id' => $user->getId(),
                    'tournament_id' => $tournament->getId(),
                ]);

                return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
            } catch (AlreadyRegisteredException) {
                $this->addFlash('error', $this->translator->trans('flash.error.already_registered'));

                return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
            }
        }

        return $this->render('tournament/register.html.twig', [
            'tournament' => $tournament,
            'form' => $form,
            'heroesData' => Faction::getHeroesGroupedByFaction($tournament->getFormat()),
        ]);
    }

    #[Route('/{id}/unregister', name: 'tournament_unregister', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function unregister(Request $request, Tournament $tournament): Response
    {
        // CSRF protection
        if (!$this->isCsrfTokenValid('unregister-' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->registrationService->unregisterPlayer($user, $tournament);
            $this->addFlash('success', $this->translator->trans('flash.success.unregistration_success'));
            $this->logger->info('Player unregistered from tournament', [
                'player_id' => $user->getId(),
                'tournament_id' => $tournament->getId(),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/registration/edit', name: 'tournament_registration_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function editRegistration(Request $request, Tournament $tournament): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Find the user's registration
        $registration = $this->registrationRepository->findOneByTournamentAndPlayer($tournament, $user);

        if ($registration === null) {
            $this->addFlash('error', $this->translator->trans('flash.error.not_registered'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        // Only allow editing if tournament is still PUBLISHED
        if ($tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            $this->addFlash('error', $this->translator->trans('flash.error.cannot_edit_after_start'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        // Pre-fill the form with current registration data
        $form = $this->createForm(RegistrationType::class, null, [
            'initial_faction' => $registration->getFaction()?->value,
            'initial_faction2' => $registration->getFaction2()?->value,
            'initial_hero' => $registration->getHero(),
            'initial_decklist_url' => $registration->getDecklistUrl(),
            'tournament_format' => $tournament->getFormat(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get faction from string value
            $factionValue = $form->get('faction')->getData();
            $faction = $factionValue ? Faction::tryFrom($factionValue) : null;

            $faction2Value = $form->get('faction2')->getData();
            $faction2 = $faction2Value ? Faction::tryFrom($faction2Value) : null;

            $this->registrationService->updateRegistration(
                $registration,
                $form->get('decklistUrl')->getData(),
                $faction,
                $form->get('hero')->getData(),
                $faction2
            );

            $this->addFlash('success', $this->translator->trans('flash.success.registration_updated'));
            $this->logger->info('Player updated registration', [
                'player_id' => $user->getId(),
                'tournament_id' => $tournament->getId(),
            ]);

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        return $this->render('tournament/edit_registration.html.twig', [
            'tournament' => $tournament,
            'registration' => $registration,
            'form' => $form,
            'heroesData' => Faction::getHeroesGroupedByFaction($tournament->getFormat()),
        ]);
    }

    #[Route('/unregister/{token}', name: 'tournament_unregister_token', methods: ['GET'])]
    public function unregisterByToken(string $token): Response
    {
        $registration = $this->registrationRepository->findByUnregisterToken($token);

        if ($registration === null) {
            throw $this->createNotFoundException('Lien de desinscription invalide ou expire.');
        }

        $tournament = $registration->getTournament();

        // Only allow unregistration if tournament is still PUBLISHED
        if ($tournament->getStatus() !== TournamentStatus::PUBLISHED) {
            $this->addFlash('error', $this->translator->trans('flash.error.cannot_unregister_after_start'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        try {
            $this->registrationService->unregisterPlayer($registration->getPlayer(), $tournament);
            $this->addFlash('success', $this->translator->trans('flash.success.unregistration_success'));
            $this->logger->info('Player unregistered via email link', [
                'player_id' => $registration->getPlayer()->getId(),
                'tournament_id' => $tournament->getId(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
    }
}
