<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ApiAccessRequest;
use App\Entity\ApiCredential;
use App\Enum\ApiRequestStatus;
use App\Repository\ApiAccessRequestRepository;
use App\Repository\ApiCredentialRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/api-access')]
#[IsGranted('ROLE_ADMIN')]
class AdminApiAccessController extends AbstractController
{
    public function __construct(
        private readonly ApiAccessRequestRepository $requestRepository,
        private readonly ApiCredentialRepository $credentialRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailService $emailService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_api_access')]
    public function index(Request $request): Response
    {
        $statusFilter = $request->query->get('status');
        $page = $request->query->getInt('page', 1);

        $status = null;
        if ($statusFilter && ApiRequestStatus::tryFrom($statusFilter)) {
            $status = ApiRequestStatus::from($statusFilter);
        }

        $result = $this->requestRepository->searchRequests($status, $page, 20);
        $pendingCount = $this->requestRepository->countPending();
        $credentialStats = $this->credentialRepository->getStatistics();

        return $this->render('admin/api/index.html.twig', [
            'requests' => $result['requests'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / 20),
            'statusFilter' => $statusFilter,
            'pendingCount' => $pendingCount,
            'credentialStats' => $credentialStats,
            'statuses' => ApiRequestStatus::cases(),
        ]);
    }

    #[Route('/{id}', name: 'admin_api_access_view', methods: ['GET'])]
    public function view(ApiAccessRequest $apiRequest): Response
    {
        // Get credential if approved
        $credential = null;
        if ($apiRequest->isApproved()) {
            $credential = $this->credentialRepository->findActiveByUser($apiRequest->getUser());
        }

        return $this->render('admin/api/view.html.twig', [
            'request' => $apiRequest,
            'credential' => $credential,
            'statuses' => ApiRequestStatus::cases(),
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_api_access_approve', methods: ['POST'])]
    public function approve(ApiAccessRequest $apiRequest, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('approve-api-' . $apiRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('admin_api_access_view', ['id' => $apiRequest->getId()]);
        }

        if (!$apiRequest->isPending()) {
            $this->addFlash('warning', $this->translator->trans('api.admin.error.not_pending'));

            return $this->redirectToRoute('admin_api_access_view', ['id' => $apiRequest->getId()]);
        }

        // Approve the request
        $apiRequest->approve($this->getUser());

        // Create the API credential
        $credential = new ApiCredential();
        $credential->setUser($apiRequest->getUser());
        $credential->setRequest($apiRequest);
        $credential->setName($apiRequest->getProjectName());

        $this->entityManager->persist($credential);
        $this->entityManager->flush();

        // Notify the user
        try {
            $this->emailService->sendApiRequestApproved($apiRequest, $credential);
        } catch (\Exception) {
            // Silent fail - don't prevent admin action
        }

        $this->addFlash('success', $this->translator->trans('api.admin.approved_success'));

        return $this->redirectToRoute('admin_api_access_view', ['id' => $apiRequest->getId()]);
    }

    #[Route('/{id}/reject', name: 'admin_api_access_reject', methods: ['POST'])]
    public function reject(ApiAccessRequest $apiRequest, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reject-api-' . $apiRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('admin_api_access_view', ['id' => $apiRequest->getId()]);
        }

        if (!$apiRequest->isPending()) {
            $this->addFlash('warning', $this->translator->trans('api.admin.error.not_pending'));

            return $this->redirectToRoute('admin_api_access_view', ['id' => $apiRequest->getId()]);
        }

        $reason = $request->request->get('reason', '');

        if (empty(trim($reason))) {
            $this->addFlash('error', $this->translator->trans('api.admin.error.reason_required'));

            return $this->redirectToRoute('admin_api_access_view', ['id' => $apiRequest->getId()]);
        }

        $apiRequest->reject($this->getUser(), $reason);
        $this->entityManager->flush();

        // Notify the user
        try {
            $this->emailService->sendApiRequestRejected($apiRequest);
        } catch (\Exception) {
            // Silent fail - don't prevent admin action
        }

        $this->addFlash('success', $this->translator->trans('api.admin.rejected_success'));

        return $this->redirectToRoute('admin_api_access');
    }

    #[Route('/credential/{id}/revoke', name: 'admin_api_credential_revoke', methods: ['POST'])]
    public function revoke(ApiCredential $credential, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('revoke-api-' . $credential->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('admin_api_access');
        }

        if ($credential->isRevoked()) {
            $this->addFlash('warning', $this->translator->trans('api.admin.error.already_revoked'));

            return $this->redirectToRoute('admin_api_access');
        }

        // Revoke the credential
        $credential->revoke();

        // Update the request status
        $apiRequest = $credential->getRequest();
        $apiRequest->setStatus(ApiRequestStatus::REVOKED);

        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('api.admin.revoked_success'));

        return $this->redirectToRoute('admin_api_access');
    }
}
