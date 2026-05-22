<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserOAuthLink;
use PHPUnit\Framework\TestCase;

final class UserOAuthLinkTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $link = new UserOAuthLink();

        $this->assertInstanceOf(UserOAuthLink::class, $link);
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $link = new UserOAuthLink();

        $this->assertNull($link->getId());
    }

    public function testProviderDefaultsToAlteredReunion(): void
    {
        $link = new UserOAuthLink();

        $this->assertSame('altered_reunion', $link->getProvider());
    }

    public function testProviderCanBeSetAndGet(): void
    {
        $link = new UserOAuthLink();
        $link->setProvider('google');

        $this->assertSame('google', $link->getProvider());
    }

    public function testProviderUserIdCanBeSetAndGet(): void
    {
        $link = new UserOAuthLink();
        $link->setProviderUserId('user-123-abc');

        $this->assertSame('user-123-abc', $link->getProviderUserId());
    }

    public function testProviderUsernameCanBeSetAndGet(): void
    {
        $link = new UserOAuthLink();
        $link->setProviderUsername('john_doe');

        $this->assertSame('john_doe', $link->getProviderUsername());
    }

    public function testProviderUsernameIsNullByDefault(): void
    {
        $link = new UserOAuthLink();

        $this->assertNull($link->getProviderUsername());
    }

    public function testLinkedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $link = new UserOAuthLink();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $link->getLinkedAt());
        $this->assertLessThanOrEqual($after, $link->getLinkedAt());
    }

    public function testUserCanBeSetAndGet(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPseudo('TestUser');

        $link = new UserOAuthLink();
        $link->setUser($user);

        $this->assertSame($user, $link->getUser());
    }
}
