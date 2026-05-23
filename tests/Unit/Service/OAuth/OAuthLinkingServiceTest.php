<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth;

use App\Entity\User;
use App\Entity\UserOAuthLink;
use App\Repository\UserOAuthLinkRepository;
use App\Service\OAuth\OAuthLinkingException;
use App\Service\OAuth\OAuthLinkingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class OAuthLinkingServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserOAuthLinkRepository&MockObject $repository;
    private LoggerInterface&MockObject $logger;
    private OAuthLinkingService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(UserOAuthLinkRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OAuthLinkingService(
            $this->entityManager,
            $this->repository,
            $this->logger,
        );
    }

    public function testLinkAccountCreatesNewLink(): void
    {
        $user = $this->createUser(1);
        $claims = [
            'sub' => 'provider-user-123',
            'email' => 'test@example.com',
            'preferred_username' => 'testuser',
        ];

        $this->repository
            ->expects($this->once())
            ->method('findByProviderUserId')
            ->with('altered_reunion', 'provider-user-123')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('findByUserAndProvider')
            ->with($user, 'altered_reunion')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $link = $this->service->linkAccount($user, 'altered_reunion', $claims);

        $this->assertSame('provider-user-123', $link->getProviderUserId());
        $this->assertSame('testuser', $link->getProviderUsername());
    }

    public function testLinkAccountThrowsExceptionWhenProviderUserAlreadyLinkedToOther(): void
    {
        $user = $this->createUser(1);
        $otherUser = $this->createUser(2);

        $existingLink = new UserOAuthLink();
        $existingLink->setUser($otherUser);
        $existingLink->setProviderUserId('provider-user-123');

        $claims = [
            'sub' => 'provider-user-123',
            'email' => 'test@example.com',
        ];

        $this->repository
            ->expects($this->once())
            ->method('findByProviderUserId')
            ->willReturn($existingLink);

        $this->expectException(OAuthLinkingException::class);
        $this->expectExceptionMessage('Ce compte Altered Re:Union est déjà lié à un autre utilisateur.');

        $this->service->linkAccount($user, 'altered_reunion', $claims);
    }

    public function testLinkAccountUpdatesUsernameWhenAlreadyLinkedToSameUser(): void
    {
        $user = $this->createUser(1);

        $existingLink = new UserOAuthLink();
        $existingLink->setUser($user);
        $existingLink->setProviderUserId('provider-user-123');
        $existingLink->setProviderUsername('old_username');

        $claims = [
            'sub' => 'provider-user-123',
            'email' => 'test@example.com',
            'preferred_username' => 'new_username',
        ];

        $this->repository
            ->expects($this->once())
            ->method('findByProviderUserId')
            ->willReturn($existingLink);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $link = $this->service->linkAccount($user, 'altered_reunion', $claims);

        $this->assertSame('new_username', $link->getProviderUsername());
    }

    public function testUnlinkAccountRemovesLink(): void
    {
        $user = $this->createUser(1);
        $user->setCreatedViaOauth(false);

        $link = new UserOAuthLink();
        $link->setUser($user);
        $link->setProviderUserId('provider-user-123');
        $user->addOauthLink($link);

        $this->repository
            ->expects($this->once())
            ->method('findByUserAndProvider')
            ->with($user, 'altered_reunion')
            ->willReturn($link);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($link);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->unlinkAccount($user, 'altered_reunion');
    }

    public function testUnlinkAccountThrowsExceptionWhenNoLink(): void
    {
        $user = $this->createUser(1);

        $this->repository
            ->expects($this->once())
            ->method('findByUserAndProvider')
            ->willReturn(null);

        $this->expectException(OAuthLinkingException::class);
        $this->expectExceptionMessage('Aucun compte lié pour ce fournisseur.');

        $this->service->unlinkAccount($user, 'altered_reunion');
    }

    public function testUnlinkAccountThrowsExceptionWhenOAuthUserWithoutPassword(): void
    {
        $user = $this->createUser(1);
        $user->setCreatedViaOauth(true);
        $user->setPassword('OAUTH_NO_PASSWORD');

        $link = new UserOAuthLink();
        $link->setUser($user);
        $user->addOauthLink($link);

        $this->repository
            ->expects($this->once())
            ->method('findByUserAndProvider')
            ->willReturn($link);

        $this->expectException(OAuthLinkingException::class);
        $this->expectExceptionMessage('Vous devez d\'abord définir un mot de passe pour votre compte.');

        $this->service->unlinkAccount($user, 'altered_reunion');
    }

    private function createUser(int $id): User
    {
        $user = new User();
        $user->setEmail("user{$id}@example.com");
        $user->setPseudo("User{$id}");
        $user->setPassword('hashed_password');

        // Use reflection to set the ID
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, $id);

        return $user;
    }
}
