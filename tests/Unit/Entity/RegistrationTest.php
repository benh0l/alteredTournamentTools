<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Registration
 */
final class RegistrationTest extends TestCase
{
    public function testConstructorSetsRequiredFields(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);

        $this->assertSame($tournament, $registration->getTournament());
        $this->assertSame($player, $registration->getPlayer());
        $this->assertInstanceOf(\DateTimeImmutable::class, $registration->getRegisteredAt());
        $this->assertNull($registration->getDecklistUrl());
    }

    public function testGetIdReturnsNullBeforePersistence(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);

        $this->assertNull($registration->getId());
    }

    public function testSetAndGetDecklistUrl(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);

        $url = 'https://altered.gg/deck/abc123';
        $registration->setDecklistUrl($url);

        $this->assertSame($url, $registration->getDecklistUrl());
    }

    public function testDecklistUrlCanBeSetToNull(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);
        $registration->setDecklistUrl('https://example.com');
        $registration->setDecklistUrl(null);

        $this->assertNull($registration->getDecklistUrl());
    }

    public function testSetDecklistUrlReturnsSelf(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);
        $result = $registration->setDecklistUrl('https://example.com');

        $this->assertSame($registration, $result);
    }

    public function testRegisteredAtIsSetToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();

        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);
        $registration = new Registration($tournament, $player);

        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $registration->getRegisteredAt());
        $this->assertLessThanOrEqual($after, $registration->getRegisteredAt());
    }

    public function testUnregisterTokenIsGeneratedOnConstruction(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);

        $this->assertNotEmpty($registration->getUnregisterToken());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $registration->getUnregisterToken()
        );
    }

    public function testEachRegistrationHasUniqueToken(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration1 = new Registration($tournament, $player);
        $registration2 = new Registration($tournament, $player);

        $this->assertNotEquals($registration1->getUnregisterToken(), $registration2->getUnregisterToken());
    }
}
