<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Entity\User;
use App\Service\OAuth\OAuthFeatureService;
use App\Service\OAuth\OAuthLinkingException;
use App\Service\OAuth\OAuthLinkingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OAuthProfileController extends AbstractController
{
    public function __construct(
        private readonly OAuthLinkingService $linkingService,
        private readonly OAuthFeatureService $featureService,
    ) {
    }

    #[Route('/profile/oauth/unlink/{provider}', name: 'profile_oauth_unlink', methods: ['POST'])]
    public function unlink(Request $request, string $provider): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // CSRF validation
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('oauth_unlink_' . $provider, $token)) {
            $this->addFlash('error', 'Token de securite invalide.');

            return $this->redirectToRoute('app_profile');
        }

        if (!$this->featureService->isProviderEnabled($provider)) {
            $this->addFlash('error', 'Ce fournisseur OAuth n\'est pas disponible.');

            return $this->redirectToRoute('app_profile');
        }

        try {
            $this->linkingService->unlinkAccount($user, $provider);
            $this->addFlash('success', 'Votre compte a ete delie avec succes.');
        } catch (OAuthLinkingException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile');
    }
}
