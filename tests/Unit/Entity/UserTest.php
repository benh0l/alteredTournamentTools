<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for User entity.
 */
final class UserTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $user = new User();

        $this->assertInstanceOf(User::class, $user);
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
    }

    public function testEmailCanBeSetAndGet(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->assertSame('test@example.com', $user->getEmail());
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->assertSame('test@example.com', $user->getUserIdentifier());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesIncludesRoleUserEvenWhenEmpty(): void
    {
        $user = new User();
        $user->setRoles([]);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    public function testAddRole(): void
    {
        $user = new User();
        $user->addRole('ROLE_ORGANIZER');

        $this->assertTrue($user->hasRole('ROLE_ORGANIZER'));
    }

    public function testAddRoleDoesNotDuplicate(): void
    {
        $user = new User();
        $user->addRole('ROLE_ORGANIZER');
        $user->addRole('ROLE_ORGANIZER');

        $roles = $user->getRoles();
        $organizerCount = count(array_filter($roles, fn ($r) => $r === 'ROLE_ORGANIZER'));

        $this->assertSame(1, $organizerCount);
    }

    public function testRemoveRole(): void
    {
        $user = new User();
        $user->addRole('ROLE_ORGANIZER');
        $user->removeRole('ROLE_ORGANIZER');

        $this->assertFalse($user->hasRole('ROLE_ORGANIZER'));
    }

    public function testCannotRemoveRoleUser(): void
    {
        $user = new User();
        $user->removeRole('ROLE_USER');

        $this->assertTrue($user->hasRole('ROLE_USER'));
    }

    public function testHasRole(): void
    {
        $user = new User();

        $this->assertTrue($user->hasRole('ROLE_USER'));
        $this->assertFalse($user->hasRole('ROLE_ADMIN'));

        $user->addRole('ROLE_ADMIN');
        $this->assertTrue($user->hasRole('ROLE_ADMIN'));
    }

    public function testPasswordCanBeSetAndGet(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');

        $this->assertSame('hashed_password', $user->getPassword());
    }

    public function testPseudoCanBeSetAndGet(): void
    {
        $user = new User();
        $user->setPseudo('JohnDoe');

        $this->assertSame('JohnDoe', $user->getPseudo());
    }

    public function testNameCanBeSetAndGet(): void
    {
        $user = new User();
        $user->setName('John Doe');

        $this->assertSame('John Doe', $user->getName());
    }

    public function testNameIsNullByDefault(): void
    {
        $user = new User();

        $this->assertNull($user->getName());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $user = new User();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $user->getCreatedAt());
        $this->assertLessThanOrEqual($after, $user->getCreatedAt());
    }

    public function testUpdatedAtIsNullByDefault(): void
    {
        $user = new User();

        $this->assertNull($user->getUpdatedAt());
    }

    public function testUpdatedAtCanBeSet(): void
    {
        $user = new User();
        $now = new \DateTimeImmutable();
        $user->setUpdatedAt($now);

        $this->assertSame($now, $user->getUpdatedAt());
    }

    public function testIsVerifiedIsFalseByDefault(): void
    {
        $user = new User();

        $this->assertFalse($user->isVerified());
    }

    public function testIsVerifiedCanBeSet(): void
    {
        $user = new User();
        $user->setIsVerified(true);

        $this->assertTrue($user->isVerified());
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();

        // Should not throw any exception
        $user->eraseCredentials();

        $this->assertTrue(true);
    }
}
