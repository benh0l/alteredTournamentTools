<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin user management controller (FR74).
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TournamentRepository $tournamentRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_users')]
    public function index(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $role = $request->query->get('role', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $result = $this->userRepository->searchUsers(
            $query ?: null,
            $role ?: null,
            $page,
            $limit
        );

        $users = $result['users'];
        $total = $result['total'];
        $totalPages = (int) ceil($total / $limit);

        // Calculate dispute counts for each user
        $disputeCounts = [];
        foreach ($users as $user) {
            $disputeCounts[$user->getId()] = $this->userRepository->countDisputesForUser($user);
        }

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'disputeCounts' => $disputeCounts,
            'query' => $query,
            'role' => $role,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/{id}', name: 'admin_user_view', requirements: ['id' => '\d+'])]
    public function view(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Get user statistics
        $registrations = $this->registrationRepository->findByPlayer($user);
        $organizedTournaments = $this->tournamentRepository->findByOrganizer($user);
        $disputedMatches = $this->userRepository->findDisputedMatchesForUser($user);
        $disputeCount = count($disputedMatches);

        // Calculate total matches
        $totalMatches = 0;
        foreach ($registrations as $reg) {
            $totalMatches += count($reg->getTournament()->getRounds());
        }

        return $this->render('admin/users/view.html.twig', [
            'user' => $user,
            'registrations' => $registrations,
            'organizedTournaments' => $organizedTournaments,
            'disputedMatches' => $disputedMatches,
            'disputeCount' => $disputeCount,
            'totalMatches' => $totalMatches,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Prevent editing yourself (to avoid locking yourself out)
        if ($user === $this->getUser()) {
            $this->addFlash('warning', $this->translator->trans('flash.error.cannot_edit_own_roles'));

            return $this->redirectToRoute('admin_user_view', ['id' => $id]);
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_user_edit', $submittedToken)) {
                throw $this->createAccessDeniedException('Invalid CSRF token');
            }

            $newRoles = $request->request->all('roles');
            if (!is_array($newRoles)) {
                $newRoles = [];
            }

            // Always keep ROLE_USER
            if (!in_array('ROLE_USER', $newRoles, true)) {
                $newRoles[] = 'ROLE_USER';
            }

            $oldRoles = $user->getRoles();
            $user->setRoles(array_unique($newRoles));

            $this->entityManager->flush();

            $this->logger->info('Admin updated user roles', [
                'admin_id' => $this->getUser()->getId(),
                'user_id' => $user->getId(),
                'old_roles' => $oldRoles,
                'new_roles' => $user->getRoles(),
            ]);

            $this->addFlash('success', $this->translator->trans('flash.success.user_roles_updated'));

            return $this->redirectToRoute('admin_user_view', ['id' => $id]);
        }

        // Get dispute history for display
        $disputedMatches = $this->userRepository->findDisputedMatchesForUser($user);

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'disputedMatches' => $disputedMatches,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Prevent deleting yourself
        if ($user === $this->getUser()) {
            $this->addFlash('error', $this->translator->trans('flash.error.cannot_delete_own_account'));

            return $this->redirectToRoute('admin_users');
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_user_delete', $submittedToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $userId = $user->getId();
        $userEmail = $user->getEmail();
        $userPseudo = $user->getPseudo();

        // Handle organized tournaments (transfer to system or anonymize)
        $organizedTournaments = $this->tournamentRepository->findByOrganizer($user);
        foreach ($organizedTournaments as $tournament) {
            // For RGPD compliance, we anonymize the tournament rather than delete
            // The tournament data is valuable for statistics
            // Set organizer to null (the entity should support this)
            // In production, you might transfer to a "deleted-user" system account
            $tournament->setOrganizer(null);
        }

        // Registrations are cascade deleted via entity mapping
        // Match history is preserved but anonymized (player references become null)

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->logger->warning('Admin deleted user account (RGPD)', [
            'admin_id' => $this->getUser()->getId(),
            'deleted_user_id' => $userId,
            'deleted_user_email' => $userEmail,
            'deleted_user_pseudo' => $userPseudo,
            'organized_tournaments_count' => count($organizedTournaments),
        ]);

        $this->addFlash('success', $this->translator->trans('flash.success.user_deleted_gdpr'));

        return $this->redirectToRoute('admin_users');
    }
}
