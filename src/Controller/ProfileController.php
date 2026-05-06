<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordProfileFormType;
use App\Form\DeleteAccountFormType;
use App\Form\ProfileEditFormType;
use App\Form\SetPasswordFormType;
use App\Repository\UserRepository;
use App\Service\AccountDeletionService;
use App\Service\AvatarUploadService;
use App\Service\OAuth\OAuthFeatureService;
use App\Service\PlayerStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AvatarUploadService $avatarUploadService,
        private readonly TranslatorInterface $translator,
        private readonly OAuthFeatureService $oauthFeatureService,
    ) {
    }

    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function show(): Response
    {
        return $this->render('profile/show.html.twig', [
            'user' => $this->getUser(),
            'oauth_altered_reunion_enabled' => $this->oauthFeatureService->isAlteredReunionEnabled(),
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $user = $this->getUser();
        $originalEmail = $user->getEmail();

        $form = $this->createForm(ProfileEditFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEmail = $form->get('email')->getData();

            // If email changed, verify current password
            if ($newEmail !== $originalEmail) {
                $currentPassword = $form->get('currentPassword')->getData();

                if (empty($currentPassword)) {
                    $this->addFlash('error', $this->translator->trans('flash.error.password_required_for_email'));

                    return $this->render('profile/edit.html.twig', [
                        'form' => $form,
                        'originalEmail' => $originalEmail,
                    ]);
                }

                if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', $this->translator->trans('flash.error.current_password_incorrect'));

                    return $this->render('profile/edit.html.twig', [
                        'form' => $form,
                        'originalEmail' => $originalEmail,
                    ]);
                }
            }

            // Handle avatar upload
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                // Delete old avatar if exists
                $this->avatarUploadService->delete($user->getAvatar());
                // Upload new avatar
                $newFilename = $this->avatarUploadService->upload($avatarFile);
                $user->setAvatar($newFilename);
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('flash.success.profile_updated'));

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'originalEmail' => $originalEmail,
        ]);
    }

    #[Route('/set-password', name: 'app_profile_set_password', methods: ['GET', 'POST'])]
    public function setPassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Only allow if user was created via OAuth and has no local password
        if (!$user->isCreatedViaOauth() || $user->hasLocalPassword()) {
            $this->addFlash('info', 'Vous avez deja un mot de passe. Utilisez "Modifier le mot de passe" pour le changer.');

            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(SetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $form->get('newPassword')->getData(),
            );
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a ete defini avec succes. Vous pouvez maintenant vous connecter avec votre email et mot de passe.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/set_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordProfileFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Verify current password
            $currentPassword = $form->get('currentPassword')->getData();

            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', $this->translator->trans('flash.error.current_password_incorrect'));

                return $this->render('profile/change_password.html.twig', [
                    'form' => $form,
                ]);
            }

            // Hash and set new password
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $form->get('newPassword')->getData(),
            );
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('flash.success.password_changed'));

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/delete', name: 'app_profile_delete', methods: ['GET', 'POST'])]
    public function delete(
        Request $request,
        AccountDeletionService $deletionService,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(DeleteAccountFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Verify password
            $password = $form->get('password')->getData();

            if (!$this->passwordHasher->isPasswordValid($user, $password)) {
                $this->addFlash('error', $this->translator->trans('flash.error.password_incorrect'));

                return $this->render('profile/delete.html.twig', [
                    'form' => $form,
                ]);
            }

            // Delete account and all related data
            $deletionService->deleteAccount($user);

            // Terminate session
            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            // Clear remember-me cookie
            $response = $this->redirectToRoute('app_home');
            $response->headers->clearCookie('REMEMBERME');

            $this->addFlash('success', $this->translator->trans('flash.success.account_deleted'));

            return $response;
        }

        return $this->render('profile/delete.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/export', name: 'app_profile_export', methods: ['GET'])]
    public function export(AccountDeletionService $deletionService): Response
    {
        $user = $this->getUser();
        $data = $deletionService->exportUserData($user);

        $response = new JsonResponse($data);
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="mes-donnees-%s.json"', date('Y-m-d')),
        );

        return $response;
    }

    #[Route('/match-history', name: 'app_profile_match_history', methods: ['GET'])]
    public function matchHistory(PlayerStatsService $statsService): Response
    {
        $user = $this->getUser();
        $stats = $statsService->getPlayerStats($user);

        return $this->render('profile/match_history.html.twig', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    #[Route('/head-to-head/{opponentId}', name: 'app_profile_head_to_head', methods: ['GET'], requirements: ['opponentId' => '\d+'])]
    public function headToHead(
        int $opponentId,
        UserRepository $userRepository,
        PlayerStatsService $statsService,
    ): Response {
        $user = $this->getUser();
        $opponent = $userRepository->find($opponentId);

        if (!$opponent) {
            throw $this->createNotFoundException('Joueur non trouvé');
        }

        // Don't allow viewing H2H against yourself
        if ($opponent->getId() === $user->getId()) {
            return $this->redirectToRoute('app_profile_match_history');
        }

        $stats = $statsService->getHeadToHeadStats($user, $opponent);

        return $this->render('profile/head_to_head.html.twig', [
            'user' => $user,
            'opponent' => $opponent,
            'stats' => $stats,
        ]);
    }

    #[Route('/search-players', name: 'app_profile_search_players', methods: ['GET'])]
    public function searchPlayers(
        Request $request,
        UserRepository $userRepository,
    ): JsonResponse {
        $query = $request->query->get('q', '');
        $currentUser = $this->getUser();

        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $result = $userRepository->searchUsers($query, null, 1, 10);
        $users = [];

        foreach ($result['users'] as $user) {
            // Exclude current user and guest accounts from search
            if ($user->getId() === $currentUser->getId() || $user->isGuest()) {
                continue;
            }

            $users[] = [
                'id' => $user->getId(),
                'displayName' => $user->getDisplayName(),
                'pseudo' => $user->getPseudo(),
                'avatar' => $user->getAvatar(),
            ];
        }

        return new JsonResponse($users);
    }
}
