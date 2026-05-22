<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ApiAccessRequest;
use App\Form\ApiAccessRequestType;
use App\Repository\ApiAccessRequestRepository;
use App\Repository\ApiCredentialRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile/api')]
#[IsGranted('ROLE_USER')]
final class ApiAccessController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiAccessRequestRepository $requestRepository,
        private readonly ApiCredentialRepository $credentialRepository,
        private readonly EmailService $emailService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'user_api_access', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        // Get user's API credential if approved
        $credential = $this->credentialRepository->findActiveByUser($user);

        // Get user's requests history
        $requests = $this->requestRepository->findByUser($user);

        // Check if user has a pending request
        $hasPendingRequest = false;
        foreach ($requests as $request) {
            if ($request->isPending()) {
                $hasPendingRequest = true;
                break;
            }
        }

        return $this->render('api/index.html.twig', [
            'credential' => $credential,
            'requests' => $requests,
            'hasPendingRequest' => $hasPendingRequest,
        ]);
    }

    #[Route('/request', name: 'user_api_request', methods: ['GET', 'POST'])]
    public function request(Request $httpRequest): Response
    {
        $user = $this->getUser();

        // Check if user already has an active request or approved credential
        if ($this->requestRepository->hasActiveRequest($user)) {
            $this->addFlash('warning', $this->translator->trans('api.flash.already_has_request'));

            return $this->redirectToRoute('user_api_access');
        }

        $apiRequest = new ApiAccessRequest();
        $apiRequest->setUser($user);

        $form = $this->createForm(ApiAccessRequestType::class, $apiRequest);
        $form->handleRequest($httpRequest);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($apiRequest);
            $this->entityManager->flush();

            // Notify admins
            try {
                $this->emailService->sendApiRequestSubmittedToAdmin($apiRequest);
            } catch (\Exception) {
                // Silent fail - don't prevent user from submitting
            }

            $this->addFlash('success', $this->translator->trans('api.flash.request_submitted'));

            return $this->redirectToRoute('user_api_access');
        }

        return $this->render('api/request.html.twig', [
            'form' => $form,
        ]);
    }
}
