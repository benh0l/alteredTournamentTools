<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Form\TournamentSearchType;
use App\Form\TournamentType;
use App\Repository\TournamentRepository;
use App\Security\Voter\TournamentVoter;
use App\Service\RegistrationService;
use App\Service\SwissRoundsCalculator;
use App\Service\TournamentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/tournaments')]
final class TournamentController extends AbstractController
{
    public function __construct(
        private readonly TournamentService $tournamentService,
        private readonly TournamentRepository $tournamentRepository,
        private readonly RegistrationService $registrationService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'tournament_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $form = $this->createForm(TournamentSearchType::class);
        $form->handleRequest($request);

        $tournaments = [];
        $searchResults = null;

        $lat = $form->get('lat')->getData();
        $lng = $form->get('lng')->getData();

        if ($lat && $lng) {
            // Search by location with coordinates
            $radius = (float) ($form->get('radius')->getData() ?? 50);
            $formatValue = $form->get('format')->getData();
            $format = $formatValue ? TournamentFormat::tryFrom($formatValue) : null;

            // Handle date filters (dateFrom = minimum, dateTo = maximum, both optional)
            $dateFrom = $form->get('dateFrom')->getData();
            $dateTo = $form->get('dateTo')->getData();

            if ($dateFrom instanceof \DateTime) {
                $dateFrom = \DateTimeImmutable::createFromMutable($dateFrom);
            }
            if ($dateTo instanceof \DateTime) {
                $dateTo = \DateTimeImmutable::createFromMutable($dateTo);
            }

            $searchResults = $this->tournamentRepository->findByLocation(
                (float) $lat,
                (float) $lng,
                $radius,
                $format,
                $dateFrom,
                $dateTo
            );
        } else {
            // No location filter, show all public tournaments
            $tournaments = $this->tournamentRepository->findPublicTournaments();
        }

        return $this->render('tournament/list.html.twig', [
            'tournaments' => $tournaments,
            'searchResults' => $searchResults,
            'form' => $form,
        ]);
    }

    #[Route('/create', name: 'tournament_create', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function createChoice(): Response
    {
        return $this->render('tournament/create_choice.html.twig');
    }

    #[Route('/create/manual', name: 'tournament_create_manual', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function createManual(Request $request): Response
    {
        $tournament = new Tournament();
        $form = $this->createForm(TournamentType::class, $tournament);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $this->tournamentService->createTournament($tournament, $user);

            $this->addFlash('success', $this->translator->trans('flash.success.tournament_created'));

            return $this->redirectToRoute('tournament_show', [
                'id' => $tournament->getId(),
            ]);
        }

        return $this->render('tournament/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'tournament_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Tournament $tournament): Response
    {
        $isRegistered = false;
        $user = $this->getUser();

        if ($user instanceof User) {
            $isRegistered = $this->registrationService->isPlayerRegistered($user, $tournament);
        }

        return $this->render('tournament/show.html.twig', [
            'tournament' => $tournament,
            'is_registered' => $isRegistered,
        ]);
    }

    #[Route('/{id}/edit', name: 'tournament_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Tournament $tournament): Response
    {
        // Check authorization via Voter (organizer + tournament not started)
        $this->denyAccessUnlessGranted(TournamentVoter::EDIT, $tournament);

        $form = $this->createForm(TournamentType::class, $tournament);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tournamentService->updateTournament($tournament);

            $this->addFlash('success', $this->translator->trans('flash.success.tournament_updated'));

            return $this->redirectToRoute('tournament_show', [
                'id' => $tournament->getId(),
            ]);
        }

        return $this->render('tournament/edit.html.twig', [
            'tournament' => $tournament,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/publish', name: 'tournament_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(Request $request, Tournament $tournament): Response
    {
        // Check authorization via Voter (organizer only)
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('publish-tournament-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        try {
            $this->tournamentService->publishTournament($tournament);
            $this->addFlash('success', $this->translator->trans('flash.success.tournament_published'));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/delete', name: 'tournament_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Tournament $tournament): Response
    {
        // Check authorization via Voter (organizer + DRAFT status)
        $this->denyAccessUnlessGranted(TournamentVoter::DELETE, $tournament);

        // CSRF protection
        if (!$this->isCsrfTokenValid('delete-tournament-' . $tournament->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        try {
            $this->tournamentService->deleteTournament($tournament);
            $this->addFlash('success', $this->translator->trans('flash.success.tournament_deleted'));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
        }

        return $this->redirectToRoute('tournament_my_list');
    }

    #[Route('/my-tournaments', name: 'tournament_my_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myTournaments(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $tournaments = $this->tournamentService->getTournamentsByOrganizer($user);

        return $this->render('tournament/my_list.html.twig', [
            'tournaments' => $tournaments,
        ]);
    }

    #[Route('/api/swiss-rounds', name: 'api_swiss_rounds', methods: ['GET'])]
    public function getSwissRoundsSuggestion(
        Request $request,
        SwissRoundsCalculator $calculator
    ): JsonResponse {
        $playerCount = $request->query->getInt('playerCount', 0);
        $formatValue = $request->query->getString('format', 'BO3');

        // Validate player count
        if (!$calculator->isValidPlayerCount($playerCount)) {
            return new JsonResponse([
                'error' => sprintf(
                    'Le nombre de joueurs doit etre entre %d et %d.',
                    $calculator->getMinPlayers(),
                    $calculator->getMaxPlayers()
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Parse format (normalize to lowercase for enum matching)
        $format = MatchFormat::tryFrom(strtolower($formatValue));
        if ($format === null) {
            return new JsonResponse([
                'error' => 'Format de match invalide. Utilisez BO1 ou BO3.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $suggestion = $calculator->getSuggestion($playerCount, $format);

        return new JsonResponse($suggestion->toArray());
    }
}
