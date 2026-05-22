<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Legal pages controller for GDPR compliance.
 *
 * Provides privacy policy, cookie policy, and terms of service pages.
 */
#[Route('/legal')]
final class LegalController extends AbstractController
{
    #[Route('/privacy', name: 'legal_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    #[Route('/cookies', name: 'legal_cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->render('legal/cookies.html.twig');
    }

    #[Route('/terms', name: 'legal_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig');
    }
}
