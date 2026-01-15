<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Test controller for Stimulus functionality.
 * Can be removed after verifying Stimulus works correctly.
 */
final class TestStimulusController extends AbstractController
{
    #[Route('/test/stimulus', name: 'test_stimulus', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('test/stimulus.html.twig');
    }
}
