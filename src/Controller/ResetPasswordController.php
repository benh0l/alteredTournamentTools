<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Service\EmailService;
use App\Service\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly ResetPasswordService $resetPasswordService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailService $emailService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Display and process the forgot password form.
     */
    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            // Process the reset request (sends email if user exists)
            $this->resetPasswordService->processResetRequest($email);

            // Always redirect to check email page to prevent email enumeration
            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    /**
     * Confirmation page after submitting reset request.
     */
    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig');
    }

    /**
     * Validate token and allow password reset.
     */
    #[Route('/reset/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(Request $request, string $token): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Validate the token
        $resetRequest = $this->resetPasswordService->validateToken($token);

        if (null === $resetRequest) {
            $this->addFlash('error', $this->translator->trans('flash.error.reset_link_invalid'));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $user = $resetRequest->getUser();
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Remove the reset request
            $this->resetPasswordService->removeResetRequest($resetRequest);

            // Hash and set the new password
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->flush();

            // Send confirmation email
            $this->emailService->sendPasswordChanged($user);

            $this->addFlash('success', $this->translator->trans('flash.success.password_reset'));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }
}
