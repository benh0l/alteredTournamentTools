<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Test controller for Tailwind CSS functionality.
 * Can be removed after verifying Tailwind works correctly.
 */
final class TestTailwindController extends AbstractController
{
    #[Route('/test/tailwind', name: 'test_tailwind', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('test/tailwind.html.twig');
    }
}
