<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ChangePasswordProfileFormType;
use App\Form\DeleteAccountFormType;
use App\Form\ProfileEditFormType;
use App\Service\AccountDeletionService;
use App\Service\AvatarUploadService;
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
    ) {
    }

    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function show(): Response
    {
        return $this->render('profile/show.html.twig', [
            'user' => $this->getUser(),
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
}
