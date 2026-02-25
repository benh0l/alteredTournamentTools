<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ApiCredential;
use App\Repository\ApiCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private const HEADER_NAME = 'X-API-Key';

    public function __construct(
        private readonly ApiCredentialRepository $credentialRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only support requests with X-API-Key header that starts with the correct prefix
        $apiKey = $request->headers->get(self::HEADER_NAME);

        if ($apiKey === null) {
            return false;
        }

        return str_starts_with($apiKey, ApiCredential::getKeyPrefix());
    }

    public function authenticate(Request $request): Passport
    {
        $apiKey = $request->headers->get(self::HEADER_NAME);

        if ($apiKey === null || $apiKey === '') {
            throw new CustomUserMessageAuthenticationException('API key is missing');
        }

        // Find the credential
        $credential = $this->credentialRepository->findByApiKey($apiKey);

        if ($credential === null) {
            throw new CustomUserMessageAuthenticationException('Invalid API key');
        }

        if (!$credential->isActive()) {
            throw new CustomUserMessageAuthenticationException('API key has been revoked');
        }

        // Update last used timestamp
        $credential->updateLastUsedAt();
        $this->entityManager->flush();

        // Store credential in request attributes for rate limiting
        $request->attributes->set('api_credential', $credential);

        // Return a self-validating passport with the user
        return new SelfValidatingPassport(
            new UserBadge($credential->getUser()->getUserIdentifier(), fn () => $credential->getUser())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            [
                'error' => 'authentication_failed',
                'message' => $exception->getMessageKey(),
            ],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
