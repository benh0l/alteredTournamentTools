<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TournamentController.
 */
final class TournamentControllerTest extends WebTestCase
{
    public function testTournamentListPageLoads(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tournaments');

        $this->assertResponseIsSuccessful();
    }

    public function testCreateTournamentRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tournaments/create');

        // Should redirect to login
        $this->assertResponseRedirects();
    }

    public function testTournamentShowReturns404ForNonExistent(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tournaments/999999');

        $this->assertResponseStatusCodeSame(404);
    }
}
