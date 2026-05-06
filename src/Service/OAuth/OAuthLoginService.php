<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Entity\User;
use App\Entity\UserOAuthLink;
use App\Repository\UserOAuthLinkRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OAuthLoginService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserOAuthLinkRepository $oauthLinkRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Find a user by their OAuth link.
     */
    public function findUserByOAuthLink(string $provider, string $providerUserId): ?User
    {
        return $this->oauthLinkRepository->findUserByProviderUserId($provider, $providerUserId);
    }

    /**
     * Check if an email already exists in the database.
     */
    public function checkEmailConflict(string $email): bool
    {
        return $this->userRepository->findOneBy(['email' => $email]) !== null;
    }

    /**
     * Check if a pseudo already exists in the database.
     */
    public function checkPseudoConflict(string $pseudo): bool
    {
        return $this->userRepository->findOneBy(['pseudo' => $pseudo]) !== null;
    }

    /**
     * Get the user that has this email (for conflict resolution).
     */
    public function findUserByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy(['email' => $email]);
    }

    /**
     * Create a new user from OAuth claims.
     *
     * @param array{sub: string, email: string, preferred_username?: string, name?: string, email_verified?: bool} $claims
     *
     * @throws OAuthLoginException if there's a conflict that prevents creation
     */
    public function createUserFromOAuth(string $provider, array $claims): User
    {
        $email = $claims['email'];
        $pseudo = $claims['preferred_username'] ?? $this->generatePseudoFromEmail($email);
        $name = $claims['name'] ?? null;
        $providerUserId = $claims['sub'];

        // Check email conflict
        if ($this->checkEmailConflict($email)) {
            throw OAuthLoginException::emailConflict($email);
        }

        // Check pseudo conflict
        if ($this->checkPseudoConflict($pseudo)) {
            throw OAuthLoginException::pseudoConflict($pseudo, $claims);
        }

        // Create user
        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($pseudo);
        $user->setName($name);
        $user->setPassword('OAUTH_NO_PASSWORD'); // Placeholder - no local password
        $user->setCreatedViaOauth(true);
        $user->setIsVerified(true); // Trust OAuth provider's email verification

        // Create OAuth link
        $link = new UserOAuthLink();
        $link->setUser($user);
        $link->setProvider($provider);
        $link->setProviderUserId($providerUserId);
        $link->setProviderUsername($claims['preferred_username'] ?? null);

        $user->addOauthLink($link);

        $this->entityManager->persist($user);
        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $this->logger->info('User created via OAuth', [
            'user_id' => $user->getId(),
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);

        return $user;
    }

    /**
     * Create user with a custom pseudo (after conflict resolution).
     *
     * @param array{sub: string, email: string, preferred_username?: string, name?: string} $claims
     */
    public function createUserWithCustomPseudo(string $provider, array $claims, string $customPseudo): User
    {
        // Validate pseudo is unique
        if ($this->checkPseudoConflict($customPseudo)) {
            throw new OAuthLoginException('Ce pseudo est déjà utilisé.');
        }

        $modifiedClaims = $claims;
        $modifiedClaims['preferred_username'] = $customPseudo;

        return $this->createUserFromOAuth($provider, $modifiedClaims);
    }

    /**
     * Generate pseudo suggestions for conflict resolution.
     *
     * @return array<string>
     */
    public function generatePseudoSuggestions(string $basePseudo, int $count = 5): array
    {
        $suggestions = [];
        $i = 1;

        while (count($suggestions) < $count && $i <= 100) {
            $candidate = $basePseudo . '_' . $i;
            if (!$this->checkPseudoConflict($candidate)) {
                $suggestions[] = $candidate;
            }
            ++$i;
        }

        return $suggestions;
    }

    private function generatePseudoFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];
        // Remove invalid characters
        $pseudo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $localPart);

        // Ensure minimum length
        if (strlen($pseudo) < 3) {
            $pseudo .= '_user';
        }

        return substr($pseudo, 0, 50);
    }
}
