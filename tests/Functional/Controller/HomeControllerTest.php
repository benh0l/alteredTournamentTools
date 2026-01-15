<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Sample functional test demonstrating Symfony WebTestCase.
 */
final class HomeControllerTest extends WebTestCase
{
    public function testTailwindTestPageLoads(): void
    {
        $client = static::createClient();

        $client->request('GET', '/test/tailwind');

        // Check response is successful (200 OK)
        $this->assertResponseIsSuccessful();
    }

    public function testStimulusTestPageLoads(): void
    {
        $client = static::createClient();

        $client->request('GET', '/test/stimulus');

        $this->assertResponseIsSuccessful();
    }

    public function testMercureTestPageLoads(): void
    {
        $client = static::createClient();

        $client->request('GET', '/test/mercure');

        $this->assertResponseIsSuccessful();
    }

    public function testNotFoundPageReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/this-page-does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }
}
