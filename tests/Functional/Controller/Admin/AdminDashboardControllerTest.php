<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for AdminDashboardController (FR68).
 */
final class AdminDashboardControllerTest extends WebTestCase
{
    public function testAdminDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/dashboard');

        // Should redirect to login
        $this->assertResponseRedirects();
    }

    public function testAdminDashboardDeniedForRegularUser(): void
    {
        $client = static::createClient();

        // Create and login a regular user
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);

        // Find or create a regular user
        $user = $userRepository->findOneBy(['email' => 'regular@example.com']);
        if (!$user) {
            // Skip test if no regular user exists in test database
            $this->markTestSkipped('No regular user found in test database');
        }

        $client->loginUser($user);

        $client->request('GET', '/admin/dashboard');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminDashboardAccessibleForAdmin(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);

        // Find an admin user
        $admin = null;
        $users = $userRepository->findAll();
        foreach ($users as $user) {
            if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $admin = $user;
                break;
            }
        }

        if (!$admin) {
            // Create admin user for test
            $admin = new User();
            $admin->setEmail('admin-test@example.com');
            $admin->setPseudo('AdminTest');
            $admin->setPassword('$2y$13$hK2BZj9z.mockpasswordhash');
            $admin->setRoles(['ROLE_ADMIN']);

            $em = $container->get('doctrine')->getManager();
            $em->persist($admin);
            $em->flush();
        }

        $client->loginUser($admin);

        $client->request('GET', '/admin/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Tableau de bord administrateur');
    }

    public function testAdminDashboardDisplaysStats(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);

        // Find an admin user
        $admin = null;
        $users = $userRepository->findAll();
        foreach ($users as $user) {
            if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $admin = $user;
                break;
            }
        }

        if (!$admin) {
            $this->markTestSkipped('No admin user found in test database');
        }

        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/dashboard');

        $this->assertResponseIsSuccessful();

        // Check stats cards are present
        $this->assertSelectorExists('[data-admin-dashboard-target="activeTournaments"]');
        $this->assertSelectorExists('[data-admin-dashboard-target="totalPlayers"]');
        $this->assertSelectorExists('[data-admin-dashboard-target="pendingDisputes"]');
    }

    public function testAdminTournamentDetailsRequiresAdmin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/tournament/1');

        // Should redirect to login
        $this->assertResponseRedirects();
    }
}
