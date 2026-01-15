<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/theme')]
final class ThemeController extends AbstractController
{
    private const VALID_COLORS = ['orange', 'blue', 'green', 'purple', 'rose'];
    private const VALID_MODES = ['light', 'dark'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_theme_save', methods: ['POST'])]
    public function saveTheme(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $color = $data['color'] ?? 'orange';
        $mode = $data['mode'] ?? 'light';

        // Validation
        if (!in_array($color, self::VALID_COLORS, true)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid theme color',
            ], 400);
        }

        if (!in_array($mode, self::VALID_MODES, true)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid theme mode',
            ], 400);
        }

        // Save to database if user is logged in
        $user = $this->getUser();
        if ($user) {
            $user->setThemeColor($color);
            $user->setThemeMode($mode);
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'success' => true,
            'color' => $color,
            'mode' => $mode,
        ]);
    }

    #[Route('', name: 'api_theme_get', methods: ['GET'])]
    public function getTheme(): JsonResponse
    {
        $user = $this->getUser();

        if ($user) {
            return new JsonResponse([
                'color' => $user->getThemeColor(),
                'mode' => $user->getThemeMode(),
            ]);
        }

        return new JsonResponse([
            'color' => 'orange',
            'mode' => 'light',
        ]);
    }
}
