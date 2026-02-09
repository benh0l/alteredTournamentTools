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
 * Controller for managing privacy preferences (GDPR compliance).
 */
#[Route('/profile/privacy')]
#[IsGranted('ROLE_USER')]
final class PrivacyPreferencesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Display privacy preferences page.
     */
    #[Route('', name: 'profile_privacy', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('profile/privacy.html.twig', [
            'preferences' => $user->getPrivacySettings(),
        ]);
    }

    /**
     * Update a privacy preference via AJAX.
     */
    #[Route('/update', name: 'profile_privacy_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        // Validate CSRF token
        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->get('_token');

        if (!$this->isCsrfTokenValid('privacy-preferences', $token)) {
            return $this->json(['error' => 'Token CSRF invalide'], Response::HTTP_FORBIDDEN);
        }

        // Get JSON data
        $data = json_decode($request->getContent(), true);

        if (!isset($data['key']) || !isset($data['value'])) {
            return $this->json(['error' => 'Paramètres manquants'], Response::HTTP_BAD_REQUEST);
        }

        $key = $data['key'];
        $value = (bool) $data['value'];

        // Validate key
        $validKeys = ['show_real_name', 'show_in_results', 'show_match_history'];
        if (!in_array($key, $validKeys, true)) {
            return $this->json(['error' => 'Préférence invalide'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Update specific privacy setting
        $settings = $user->getPrivacySettings();
        $settings[$key] = $value;
        $user->setPrivacySettings($settings);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Préférences mises à jour',
            'preferences' => $user->getPrivacySettings(),
        ]);
    }
}
