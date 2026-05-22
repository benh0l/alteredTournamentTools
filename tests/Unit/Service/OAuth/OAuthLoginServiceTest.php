<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth;

use App\Entity\User;
use App\Repository\UserOAuthLinkRepository;
use App\Repository\UserRepository;
use App\Service\OAuth\OAuthLoginException;
use App\Service\OAuth\OAuthLoginService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class OAuthLoginServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&MockObject $userRepository;
    private UserOAuthLinkRepository&MockObject $oauthLinkRepository;
    private LoggerInterface&MockObject $logger;
    private OAuthLoginService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->oauthLinkRepository = $this->createMock(UserOAuthLinkRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OAuthLoginService(
            $this->entityManager,
            $this->userRepository,
            $this->oauthLinkRepository,
            $this->logger,
        );
    }

    public function testFindUserByOAuthLinkReturnsUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPseudo('TestUser');

        $this->oauthLinkRepository
            ->expects($this->once())
            ->method('findUserByProviderUserId')
            ->with('altered_reunion', 'provider-123')
            ->willReturn($user);

        $result = $this->service->findUserByOAuthLink('altered_reunion', 'provider-123');

        $this->assertSame($user, $result);
    }

    public function testFindUserByOAuthLinkReturnsNullWhenNotFound(): void
    {
        $this->oauthLinkRepository
            ->expects($this->once())
            ->method('findUserByProviderUserId')
            ->willReturn(null);

        $result = $this->service->findUserByOAuthLink('altered_reunion', 'provider-123');

        $this->assertNull($result);
    }

    public function testCheckEmailConflictReturnsTrueWhenExists(): void
    {
        $user = new User();

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'existing@example.com'])
            ->willReturn($user);

        $this->assertTrue($this->service->checkEmailConflict('existing@example.com'));
    }

    public function testCheckEmailConflictReturnsFalseWhenNotExists(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'new@example.com'])
            ->willReturn(null);

        $this->assertFalse($this->service->checkEmailConflict('new@example.com'));
    }

    public function testCheckPseudoConflictReturnsTrueWhenExists(): void
    {
        $user = new User();

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['pseudo' => 'ExistingUser'])
            ->willReturn($user);

        $this->assertTrue($this->service->checkPseudoConflict('ExistingUser'));
    }

    public function testCreateUserFromOAuthCreatesUserAndLink(): void
    {
        $claims = [
            'sub' => 'provider-123',
            'email' => 'new@example.com',
            'preferred_username' => 'newuser',
            'name' => 'New User',
        ];

        $this->userRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $user = $this->service->createUserFromOAuth('altered_reunion', $claims);

        $this->assertSame('new@example.com', $user->getEmail());
        $this->assertSame('newuser', $user->getPseudo());
        $this->assertSame('New User', $user->getName());
        $this->assertTrue($user->isCreatedViaOauth());
        $this->assertTrue($user->isVerified());
        $this->assertSame('OAUTH_NO_PASSWORD', $user->getPassword());
    }

    public function testCreateUserFromOAuthThrowsEmailConflict(): void
    {
        $existingUser = new User();

        $claims = [
            'sub' => 'provider-123',
            'email' => 'existing@example.com',
            'preferred_username' => 'newuser',
        ];

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'existing@example.com'])
            ->willReturn($existingUser);

        $this->expectException(OAuthLoginException::class);

        try {
            $this->service->createUserFromOAuth('altered_reunion', $claims);
        } catch (OAuthLoginException $e) {
            $this->assertTrue($e->isEmailConflict());
            throw $e;
        }
    }

    public function testCreateUserFromOAuthThrowsPseudoConflict(): void
    {
        $existingUser = new User();

        $claims = [
            'sub' => 'provider-123',
            'email' => 'new@example.com',
            'preferred_username' => 'existingpseudo',
        ];

        $this->userRepository
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($existingUser) {
                if (isset($criteria['email'])) {
                    return null;
                }
                if (isset($criteria['pseudo'])) {
                    return $existingUser;
                }

                return null;
            });

        $this->expectException(OAuthLoginException::class);

        try {
            $this->service->createUserFromOAuth('altered_reunion', $claims);
        } catch (OAuthLoginException $e) {
            $this->assertTrue($e->isPseudoConflict());
            throw $e;
        }
    }

    public function testGeneratePseudoSuggestionsReturnsAvailablePseudos(): void
    {
        $this->userRepository
            ->method('findOneBy')
            ->willReturnCallback(function (array $criteria) {
                // Simulate that basepseudo_1 is taken, but _2 to _5 are available
                if ($criteria['pseudo'] === 'basepseudo_1') {
                    return new User();
                }

                return null;
            });

        $suggestions = $this->service->generatePseudoSuggestions('basepseudo', 4);

        $this->assertCount(4, $suggestions);
        $this->assertNotContains('basepseudo_1', $suggestions);
        $this->assertContains('basepseudo_2', $suggestions);
    }
}
