<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Service\OAuth\OAuthFeatureService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class KeycloakConnectController extends AbstractController
{
    private const SCOPES = ['openid', 'email', 'profile', 'read-decks'];
    private const STATE_SESSION_KEY = 'oauth_state';
    private const INTENT_SESSION_KEY = 'oauth_intent';

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly OAuthFeatureService $featureService,
    ) {
    }

    /**
     * Initiate OAuth flow for login (no authentication required).
     */
    #[Route('/oauth/altered-reunion/connect', name: 'oauth_altered_reunion_connect')]
    public function connect(Request $request): Response
    {
        if (!$this->featureService->isAlteredReunionEnabled()) {
            $this->addFlash('error', 'La connexion via Altered Re:Union n\'est pas disponible.');

            return $this->redirectToRoute('app_login');
        }

        // Generate and store state
        $state = bin2hex(random_bytes(32));
        $request->getSession()->set(self::STATE_SESSION_KEY, $state);
        $request->getSession()->set(self::INTENT_SESSION_KEY, 'login');

        // Set state expiration (5 minutes)
        $request->getSession()->set('oauth_state_expires', time() + 300);

        return $this->clientRegistry
            ->getClient('altered_reunion')
            ->redirect(self::SCOPES, ['state' => $state]);
    }

    /**
     * Initiate OAuth flow for linking (authentication required).
     */
    #[Route('/oauth/altered-reunion/link', name: 'oauth_altered_reunion_link')]
    #[IsGranted('ROLE_USER')]
    public function link(Request $request): Response
    {
        if (!$this->featureService->isAlteredReunionEnabled()) {
            $this->addFlash('error', 'La liaison via Altered Re:Union n\'est pas disponible.');

            return $this->redirectToRoute('app_profile');
        }

        // Generate and store state
        $state = bin2hex(random_bytes(32));
        $request->getSession()->set(self::STATE_SESSION_KEY, $state);
        $request->getSession()->set(self::INTENT_SESSION_KEY, 'link');

        // Set state expiration (5 minutes)
        $request->getSession()->set('oauth_state_expires', time() + 300);

        return $this->clientRegistry
            ->getClient('altered_reunion')
            ->redirect(self::SCOPES, ['state' => $state]);
    }
}
