<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\Faction;
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

    public function testIsDroppedReturnsFalseByDefault(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);

        $this->assertFalse($registration->isDropped());
        $this->assertNull($registration->getDroppedAt());
    }

    public function testDropSetsDroppedAtTimestamp(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $before = new \DateTimeImmutable();
        $registration = new Registration($tournament, $player);
        $registration->drop();
        $after = new \DateTimeImmutable();

        $this->assertTrue($registration->isDropped());
        $this->assertInstanceOf(\DateTimeImmutable::class, $registration->getDroppedAt());
        $this->assertGreaterThanOrEqual($before, $registration->getDroppedAt());
        $this->assertLessThanOrEqual($after, $registration->getDroppedAt());
    }

    public function testDropReturnsSelf(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);
        $result = $registration->drop();

        $this->assertSame($registration, $result);
    }

    public function testDropDoesNotUpdateTimestampIfAlreadyDropped(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);
        $registration->drop();
        $firstDroppedAt = $registration->getDroppedAt();

        // Small delay to ensure different timestamp
        usleep(1000);
        $registration->drop();

        $this->assertSame($firstDroppedAt, $registration->getDroppedAt());
    }

    public function testUndropClearsDroppedAtTimestamp(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);
        $registration->drop();
        $this->assertTrue($registration->isDropped());

        $registration->undrop();

        $this->assertFalse($registration->isDropped());
        $this->assertNull($registration->getDroppedAt());
    }

    public function testUndropReturnsSelf(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);
        $registration->drop();
        $result = $registration->undrop();

        $this->assertSame($registration, $result);
    }

    public function testUndropWorksOnNonDroppedRegistration(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);
        $registration->undrop();

        $this->assertFalse($registration->isDropped());
        $this->assertNull($registration->getDroppedAt());
    }

    public function testDropCanBeCalledAfterUndrop(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $player = $this->createMock(User::class);

        $registration = new Registration($tournament, $player);

        // First drop
        $registration->drop();
        $this->assertTrue($registration->isDropped());

        // Undrop
        $registration->undrop();
        $this->assertFalse($registration->isDropped());

        // Drop again
        $registration->drop();
        $this->assertTrue($registration->isDropped());
        $this->assertNotNull($registration->getDroppedAt());
    }

    public function testGetMembersReturnsPlayer(): void
    {
        $player = $this->createMock(User::class);
        $registration = new Registration($this->createMock(Tournament::class), $player);

        $this->assertSame([$player], $registration->getMembers());
    }

    public function testHasMemberMatchesSameInstance(): void
    {
        $player = $this->createMock(User::class);
        $registration = new Registration($this->createMock(Tournament::class), $player);

        $this->assertTrue($registration->hasMember($player));
    }

    public function testHasMemberMatchesSameIdOnDifferentInstance(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getId')->willReturn(42);
        $sameUser = $this->createMock(User::class);
        $sameUser->method('getId')->willReturn(42);

        $registration = new Registration($this->createMock(Tournament::class), $player);

        $this->assertTrue($registration->hasMember($sameUser));
    }

    public function testHasMemberRejectsOtherUser(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getId')->willReturn(42);
        $other = $this->createMock(User::class);
        $other->method('getId')->willReturn(7);

        $registration = new Registration($this->createMock(Tournament::class), $player);

        $this->assertFalse($registration->hasMember($other));
    }

    public function testHasMemberRejectsUnpersistedOtherUser(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getId')->willReturn(null);
        $other = $this->createMock(User::class);
        $other->method('getId')->willReturn(null);

        $registration = new Registration($this->createMock(Tournament::class), $player);

        $this->assertFalse($registration->hasMember($other));
    }

    public function testHasMemberReturnsFalseForNull(): void
    {
        $registration = new Registration($this->createMock(Tournament::class), $this->createMock(User::class));

        $this->assertFalse($registration->hasMember(null));
    }

    public function testGetDisplayNameDelegatesToPlayer(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getDisplayName')->willReturn('[TAG] Pseudo');

        $registration = new Registration($this->createMock(Tournament::class), $player);

        $this->assertSame('[TAG] Pseudo', $registration->getDisplayName());
    }

    public function testPartnerIsNullByDefault(): void
    {
        $registration = new Registration($this->createMock(Tournament::class), $this->createMock(User::class));

        $this->assertNull($registration->getPartner());
        $this->assertCount(1, $registration->getMembers());
    }

    public function testPartnerIsMember(): void
    {
        [$captain, $partner] = $this->createTeamUsers();
        $registration = (new Registration($this->createMock(Tournament::class), $captain))->setPartner($partner);

        $this->assertSame([$captain, $partner], $registration->getMembers());
        $this->assertTrue($registration->hasMember($captain));
        $this->assertTrue($registration->hasMember($partner));
    }

    public function testTeamDisplayNameJoinsMembers(): void
    {
        [$captain, $partner] = $this->createTeamUsers();
        $registration = (new Registration($this->createMock(Tournament::class), $captain))->setPartner($partner);

        $this->assertSame('Alice & Bob', $registration->getDisplayName());
    }

    public function testPlayerCannotBeTheirOwnPartner(): void
    {
        $captain = $this->createMock(User::class);
        $registration = new Registration($this->createMock(Tournament::class), $captain);

        $this->expectException(\InvalidArgumentException::class);
        $registration->setPartner($captain);
    }

    public function testPartnerCanBeRemoved(): void
    {
        [$captain, $partner] = $this->createTeamUsers();
        $registration = (new Registration($this->createMock(Tournament::class), $captain))->setPartner($partner);
        $registration->setPartner(null);

        $this->assertFalse($registration->hasMember($partner));
    }

    public function testPartnerDeckFields(): void
    {
        $registration = new Registration($this->createMock(Tournament::class), $this->createMock(User::class));
        $registration
            ->setPartnerFaction(Faction::AXIOM)
            ->setPartnerFaction2(Faction::BRAVOS)
            ->setPartnerHero('sierra')
            ->setPartnerDecklistUrl('https://altered.gg/deck/xyz');

        $this->assertSame(Faction::AXIOM, $registration->getPartnerFaction());
        $this->assertSame(Faction::BRAVOS, $registration->getPartnerFaction2());
        $this->assertSame('sierra', $registration->getPartnerHero());
        $this->assertSame('https://altered.gg/deck/xyz', $registration->getPartnerDecklistUrl());
    }

    /**
     * @return array{User, User}
     */
    private function createTeamUsers(): array
    {
        $captain = $this->createMock(User::class);
        $captain->method('getId')->willReturn(1);
        $captain->method('getDisplayName')->willReturn('Alice');
        $partner = $this->createMock(User::class);
        $partner->method('getId')->willReturn(2);
        $partner->method('getDisplayName')->willReturn('Bob');

        return [$captain, $partner];
    }
}
