<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\AvatarUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly AvatarUploadService $avatarUploadService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash password with Argon2id (configured in security.yaml)
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData(),
            );
            $user->setPassword($hashedPassword);

            // Handle avatar upload if provided
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $avatarFilename = $this->avatarUploadService->upload($avatarFile);
                $user->setAvatar($avatarFilename);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Auto-login after registration (Symfony 6.2+)
            $this->security->login($user, 'form_login', 'main');

            $this->addFlash('success', $this->translator->trans('flash.success.account_created'));

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
