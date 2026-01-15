<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Sample integration test demonstrating database testing.
 *
 * Note: This requires User entity to exist (Story 2.1).
 * Placeholder until User entity is created.
 */
final class UserRepositoryTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testKernelBoots(): void
    {
        // Basic test to verify kernel boots correctly
        $this->assertNotNull(self::$kernel);
    }

    public function testContainerHasEntityManager(): void
    {
        $container = static::getContainer();

        $this->assertTrue($container->has('doctrine.orm.entity_manager'));
    }

    // Uncomment once User entity exists (Story 2.1):
    // public function testFindUserByEmail(): void
    // {
    //     $container = static::getContainer();
    //     $userRepository = $container->get(UserRepository::class);
    //
    //     // Create test user
    //     $entityManager = $container->get('doctrine.orm.entity_manager');
    //     $user = new User();
    //     $user->setEmail('test@example.com');
    //     $user->setPassword('hashed_password');
    //     $entityManager->persist($user);
    //     $entityManager->flush();
    //
    //     // Find user
    //     $foundUser = $userRepository->findOneBy(['email' => 'test@example.com']);
    //
    //     $this->assertNotNull($foundUser);
    //     $this->assertSame('test@example.com', $foundUser->getEmail());
    // }
}
