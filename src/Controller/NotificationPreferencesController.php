<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for managing notification preferences (FR67).
 */
#[Route('/profile/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationPreferencesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Display notification preferences page.
     */
    #[Route('', name: 'profile_notifications', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('profile/notifications.html.twig', [
            'preferences' => $user->getNotificationPreferences(),
        ]);
    }

    /**
     * Update a notification preference via AJAX.
     */
    #[Route('/update', name: 'profile_notifications_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        // Validate CSRF token
        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->get('_token');

        if (!$this->isCsrfTokenValid('notification-preferences', $token)) {
            return $this->json(['error' => 'Token CSRF invalide'], Response::HTTP_FORBIDDEN);
        }

        // Get JSON data
        $data = json_decode($request->getContent(), true);

        if (!isset($data['key']) || !isset($data['value'])) {
            return $this->json(['error' => 'Parametres manquants'], Response::HTTP_BAD_REQUEST);
        }

        $key = $data['key'];
        $value = (bool) $data['value'];

        // Validate key
        $validKeys = ['d_minus_1', 'h_minus_1', 'round_start', 'channels.email', 'channels.push'];
        if (!in_array($key, $validKeys, true)) {
            return $this->json(['error' => 'Preference invalide'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setNotificationPreference($key, $value);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Preferences mises a jour',
            'preferences' => $user->getNotificationPreferences(),
        ]);
    }

    /**
     * Get current preferences as JSON (for API use).
     */
    #[Route('/api', name: 'profile_notifications_api', methods: ['GET'])]
    public function api(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'preferences' => $user->getNotificationPreferences(),
        ]);
    }
}
