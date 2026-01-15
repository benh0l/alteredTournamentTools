<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\User;
use App\Service\MyTournamentsService;
use App\Service\RegistrationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/my-tournaments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class MyTournamentsController extends AbstractController
{
    public function __construct(
        private readonly MyTournamentsService $myTournamentsService,
        private readonly RegistrationService $registrationService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'my_tournaments', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $groupedTournaments = $this->myTournamentsService->getGroupedTournamentsForPlayer($user);
        $totalCount = count($groupedTournaments['upcoming'])
            + count($groupedTournaments['ongoing'])
            + count($groupedTournaments['completed']);

        return $this->render('my_tournaments/index.html.twig', [
            'grouped_tournaments' => $groupedTournaments,
            'total_count' => $totalCount,
        ]);
    }

    #[Route('/{id}/unregister', name: 'my_tournaments_unregister', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unregister(Request $request, Tournament $tournament): Response
    {
        // CSRF protection
        if (!$this->isCsrfTokenValid('unregister' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->registrationService->unregisterPlayer($user, $tournament);
            $this->addFlash('success', $this->translator->trans('flash.success.unregistration_success'));
            $this->logger->info('Player unregistered from tournament via My Tournaments page', [
                'player_id' => $user->getId(),
                'tournament_id' => $tournament->getId(),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('my_tournaments');
    }
}
