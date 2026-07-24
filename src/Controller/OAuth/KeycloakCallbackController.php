<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Entity\User;
use App\Service\OAuth\OAuthFeatureService;
use App\Service\OAuth\OAuthLinkingException;
use App\Service\OAuth\OAuthLinkingService;
use App\Service\OAuth\OAuthLoginException;
use App\Service\OAuth\OAuthLoginService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;

class KeycloakCallbackController extends AbstractController
{
    private const STATE_SESSION_KEY = 'oauth_state';
    private const INTENT_SESSION_KEY = 'oauth_intent';
    private const OAUTH_CLAIMS_SESSION_KEY = 'oauth_pending_claims';

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly OAuthFeatureService $featureService,
        private readonly OAuthLinkingService $linkingService,
        private readonly OAuthLoginService $loginService,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly FormLoginAuthenticator $formLoginAuthenticator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/oauth/altered-reunion/callback', name: 'oauth_altered_reunion_callback')]
    public function callback(Request $request): Response
    {
        if (!$this->featureService->isAlteredReunionEnabled()) {
            $this->addFlash('error', 'La connexion via Altered Re:Union n\'est pas disponible.');

            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();

        // Validate state
        $stateFromRequest = $request->query->get('state');
        $stateFromSession = $session->get(self::STATE_SESSION_KEY);
        $stateExpires = $session->get('oauth_state_expires', 0);

        // Clear state from session
        $session->remove(self::STATE_SESSION_KEY);
        $session->remove('oauth_state_expires');

        if ($stateFromRequest === null || $stateFromSession === null || !hash_equals($stateFromSession, $stateFromRequest)) {
            $this->logger->warning('OAuth state validation failed', [
                'has_request_state' => $stateFromRequest !== null,
                'has_session_state' => $stateFromSession !== null,
            ]);

            $this->addFlash('error', 'La validation de sécurité a échoué. Veuillez réessayer.');

            return $this->redirectToRoute('app_login');
        }

        if (time() > $stateExpires) {
            $this->logger->warning('OAuth state expired');
            $this->addFlash('error', 'La session a expiré. Veuillez réessayer.');

            return $this->redirectToRoute('app_login');
        }

        // Check for OAuth error
        if ($request->query->has('error')) {
            $error = $request->query->get('error');
            $errorDescription = $request->query->get('error_description', 'Erreur inconnue');

            $this->logger->error('OAuth error from provider', [
                'error' => $error,
                'description' => $errorDescription,
            ]);

            $this->addFlash('error', 'Erreur d\'authentification: ' . $errorDescription);

            return $this->redirectToRoute('app_login');
        }

        // Get intent
        $intent = $session->get(self::INTENT_SESSION_KEY, 'login');
        $session->remove(self::INTENT_SESSION_KEY);

        try {
            $client = $this->clientRegistry->getClient('altered_reunion');
            $accessToken = $client->getAccessToken();
            $keycloakUser = $client->fetchUserFromToken($accessToken);

            $userData = $keycloakUser->toArray();

            // Log Keycloak response for debugging
            $this->logger->info('Keycloak user data received', [
                'keys' => array_keys($userData),
                'data' => $userData,
            ]);

            // Keycloak custom attribute: pseudo is in profile.attributes.pseudo
            $pseudo = $userData['pseudo']
                ?? $userData['attributes']['pseudo'] ?? null;

            // Fallback to preferred_username if no custom pseudo
            $displayUsername = $pseudo ?? $keycloakUser->getUsername() ?? $keycloakUser->getEmail();

            $claims = [
                'sub' => $keycloakUser->getId(),
                'email' => $keycloakUser->getEmail(),
                'preferred_username' => $displayUsername,
                'name' => $keycloakUser->getName(),
                'email_verified' => $userData['email_verified'] ?? false,
            ];

            return match ($intent) {
                'link' => $this->handleLinkIntent($claims),
                default => $this->handleLoginIntent($request, $claims),
            };
        } catch (IdentityProviderException $e) {
            $this->logger->error('OAuth token exchange failed', [
                'error' => $e->getMessage(),
            ]);

            $this->addFlash('error', 'Le service Altered Re:Union est temporairement indisponible. Veuillez réessayer plus tard.');

            return $this->redirectToRoute('app_login');
        } catch (\Exception $e) {
            $this->logger->error('Unexpected OAuth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer.');

            return $this->redirectToRoute('app_login');
        }
    }

    /**
     * @param array{sub: string, email: string, preferred_username?: string, name?: string, email_verified?: bool} $claims
     */
    private function handleLinkIntent(array $claims): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            $this->addFlash('error', 'Vous devez être connecté pour lier votre compte.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $this->linkingService->linkAccount($user, OAuthFeatureService::PROVIDER_ALTERED_REUNION, $claims);
            $this->addFlash('success', 'Votre compte Altered Re:Union a été lié avec succès.');

            return $this->redirectToRoute('app_profile');
        } catch (OAuthLinkingException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_profile');
        }
    }

    /**
     * @param array{sub: string, email: string, preferred_username?: string, name?: string, email_verified?: bool} $claims
     */
    private function handleLoginIntent(Request $request, array $claims): Response
    {
        $provider = OAuthFeatureService::PROVIDER_ALTERED_REUNION;

        // Check if user already linked
        $existingUser = $this->loginService->findUserByOAuthLink($provider, $claims['sub']);
        if ($existingUser !== null) {
            return $this->loginUser($request, $existingUser);
        }

        // Try to create new user
        try {
            $newUser = $this->loginService->createUserFromOAuth($provider, $claims);

            return $this->loginUser($request, $newUser);
        } catch (OAuthLoginException $e) {
            if ($e->isEmailConflict()) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_login');
            }

            if ($e->isPseudoConflict()) {
                // Store claims in session for pseudo choice
                $request->getSession()->set(self::OAUTH_CLAIMS_SESSION_KEY, $claims);

                return $this->redirectToRoute('oauth_choose_pseudo');
            }

            throw $e;
        }
    }

    private function loginUser(Request $request, User $user): Response
    {
        $this->userAuthenticator->authenticateUser($user, $this->formLoginAuthenticator, $request);

        $this->addFlash('success', 'Connexion réussie via Altered Re:Union.');

        return $this->redirectToRoute('app_home');
    }
}
