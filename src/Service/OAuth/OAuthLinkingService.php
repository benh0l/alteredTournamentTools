<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Entity\User;
use App\Entity\UserOAuthLink;
use App\Repository\UserOAuthLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OAuthLinkingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserOAuthLinkRepository $oauthLinkRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Link a user account to an OAuth provider.
     *
     * @param array{sub: string, email: string, preferred_username?: string, name?: string} $claims
     *
     * @throws OAuthLinkingException if the provider user ID is already linked to another account
     */
    public function linkAccount(User $user, string $provider, array $claims): UserOAuthLink
    {
        $providerUserId = $claims['sub'];
        $providerUsername = $claims['preferred_username'] ?? null;

        // Check if this provider account is already linked to another user
        $existingLink = $this->oauthLinkRepository->findByProviderUserId($provider, $providerUserId);
        if ($existingLink !== null) {
            if ($existingLink->getUser()->getId() !== $user->getId()) {
                $this->logger->warning('Attempted to link already-linked OAuth account', [
                    'provider' => $provider,
                    'provider_user_id' => $providerUserId,
                    'requesting_user_id' => $user->getId(),
                    'existing_user_id' => $existingLink->getUser()->getId(),
                ]);
                throw new OAuthLinkingException('Ce compte Altered Re:Union est déjà lié à un autre utilisateur.');
            }

            // Already linked to this user - update username if changed
            if ($providerUsername !== null && $existingLink->getProviderUsername() !== $providerUsername) {
                $existingLink->setProviderUsername($providerUsername);
                $this->entityManager->flush();
            }

            return $existingLink;
        }

        // Check if user already has a link for this provider
        $userLink = $this->oauthLinkRepository->findByUserAndProvider($user, $provider);
        if ($userLink !== null) {
            throw new OAuthLinkingException('Vous avez déjà un compte Altered Re:Union lié.');
        }

        // Create new link
        $link = new UserOAuthLink();
        $link->setUser($user);
        $link->setProvider($provider);
        $link->setProviderUserId($providerUserId);
        $link->setProviderUsername($providerUsername);

        $user->addOauthLink($link);
        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $this->logger->info('OAuth account linked', [
            'user_id' => $user->getId(),
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);

        return $link;
    }

    /**
     * Unlink a user account from an OAuth provider.
     *
     * @throws OAuthLinkingException if unlinking is not allowed
     */
    public function unlinkAccount(User $user, string $provider): void
    {
        $link = $this->oauthLinkRepository->findByUserAndProvider($user, $provider);
        if ($link === null) {
            throw new OAuthLinkingException('Aucun compte lié pour ce fournisseur.');
        }

        // Check if user can unlink (must have local password if created via OAuth)
        if ($user->isCreatedViaOauth() && !$user->hasLocalPassword()) {
            throw new OAuthLinkingException('Vous devez d\'abord définir un mot de passe pour votre compte.');
        }

        $user->removeOauthLink($link);
        $this->entityManager->remove($link);
        $this->entityManager->flush();

        $this->logger->info('OAuth account unlinked', [
            'user_id' => $user->getId(),
            'provider' => $provider,
        ]);
    }
}
