<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\AccountClaimType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for claiming guest accounts created via bulk import.
 *
 * When a guest account is created during import, a claim token is generated.
 * The user can use this token to set their pseudo and password and convert
 * their guest account to a full account.
 */
final class AccountClaimController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Show claim form and process account activation.
     */
    #[Route('/claim/{token}', name: 'account_claim', methods: ['GET', 'POST'])]
    public function claim(Request $request, string $token): Response
    {
        $user = $this->userRepository->findOneBy(['claimToken' => $token]);

        if (!$user || !$user->isClaimable()) {
            $this->addFlash('error', $this->translator->trans('claim.error.invalid_or_expired'));

            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(AccountClaimType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pseudo = $form->get('pseudo')->getData();
            $plainPassword = $form->get('password')->getData();

            // Check if pseudo is already taken
            $existingUser = $this->userRepository->findOneBy(['pseudo' => $pseudo]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('error', $this->translator->trans('claim.error.pseudo_taken'));

                return $this->render('account/claim.html.twig', [
                    'form' => $form,
                    'user' => $user,
                    'registrations' => $user->getRegistrations(),
                ]);
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->claim($pseudo, $hashedPassword);

            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('claim.success'));

            return $this->redirectToRoute('app_login');
        }

        // Get user's tournament registrations for display
        $registrations = $user->getRegistrations();

        return $this->render('account/claim.html.twig', [
            'form' => $form,
            'user' => $user,
            'registrations' => $registrations,
        ]);
    }
}
