<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\DecklistTransparency;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tournament entity.
 */
final class TournamentTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $tournament = new Tournament();

        $this->assertInstanceOf(Tournament::class, $tournament);
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $tournament = new Tournament();

        $this->assertNull($tournament->getId());
    }

    public function testDefaultStatusIsDraft(): void
    {
        $tournament = new Tournament();

        $this->assertSame(TournamentStatus::DRAFT, $tournament->getStatus());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $tournament = new Tournament();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $tournament->getCreatedAt());
        $this->assertLessThanOrEqual($after, $tournament->getCreatedAt());
    }

    public function testUpdatedAtIsNullByDefault(): void
    {
        $tournament = new Tournament();

        $this->assertNull($tournament->getUpdatedAt());
    }

    public function testNameCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setName('Winter Championship 2026');

        $this->assertSame('Winter Championship 2026', $tournament->getName());
    }

    public function testOrganizerCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $user = new User();
        $user->setEmail('organizer@test.com');
        $user->setPseudo('Organizer');
        $user->setPassword('hashed');

        $tournament->setOrganizer($user);

        $this->assertSame($user, $tournament->getOrganizer());
    }

    public function testDateCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $date = new \DateTimeImmutable('2026-02-15');
        $tournament->setDate($date);

        $this->assertSame($date, $tournament->getDate());
    }

    public function testTimeCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $time = new \DateTimeImmutable('14:30:00');
        $tournament->setTime($time);

        $this->assertSame($time, $tournament->getTime());
    }

    public function testTimeIsNullByDefault(): void
    {
        $tournament = new Tournament();

        $this->assertNull($tournament->getTime());
    }

    public function testLocationCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setLocation('Paris, France');

        $this->assertSame('Paris, France', $tournament->getLocation());
    }

    public function testLocationIsNullByDefault(): void
    {
        $tournament = new Tournament();

        $this->assertNull($tournament->getLocation());
    }

    public function testDescriptionCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setDescription('Un super tournoi Altered TCG');

        $this->assertSame('Un super tournoi Altered TCG', $tournament->getDescription());
    }

    public function testEntryFeeCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setEntryFee('10 EUR');

        $this->assertSame('10 EUR', $tournament->getEntryFee());
    }

    public function testPrizesCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setPrizes('1er: Boite, 2eme: 5 boosters');

        $this->assertSame('1er: Boite, 2eme: 5 boosters', $tournament->getPrizes());
    }

    public function testAlteredGgLinkCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setAlteredGgLink('https://altered.gg/tournament/123');

        $this->assertSame('https://altered.gg/tournament/123', $tournament->getAlteredGgLink());
    }

    public function testFormatCanBeSetAndGet(): void
    {
        $tournament = new Tournament();

        $tournament->setFormat(TournamentFormat::CONSTRUCTED);
        $this->assertSame(TournamentFormat::CONSTRUCTED, $tournament->getFormat());

        $tournament->setFormat(TournamentFormat::LIMITED);
        $this->assertSame(TournamentFormat::LIMITED, $tournament->getFormat());
    }

    public function testStructureCanBeSetAndGet(): void
    {
        $tournament = new Tournament();

        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $this->assertSame(TournamentStructure::SWISS_ONLY, $tournament->getStructure());

        $tournament->setStructure(TournamentStructure::SINGLE_ELIMINATION);
        $this->assertSame(TournamentStructure::SINGLE_ELIMINATION, $tournament->getStructure());

        $tournament->setStructure(TournamentStructure::MIXED);
        $this->assertSame(TournamentStructure::MIXED, $tournament->getStructure());
    }

    public function testVisibilityCanBeSetAndGet(): void
    {
        $tournament = new Tournament();

        $tournament->setVisibility(TournamentVisibility::PUBLIC);
        $this->assertSame(TournamentVisibility::PUBLIC, $tournament->getVisibility());

        $tournament->setVisibility(TournamentVisibility::PRIVATE);
        $this->assertSame(TournamentVisibility::PRIVATE, $tournament->getVisibility());
    }

    public function testDecklistTransparencyCanBeSetAndGet(): void
    {
        $tournament = new Tournament();

        $tournament->setDecklistTransparency(DecklistTransparency::OPEN);
        $this->assertSame(DecklistTransparency::OPEN, $tournament->getDecklistTransparency());

        $tournament->setDecklistTransparency(DecklistTransparency::CLOSED);
        $this->assertSame(DecklistTransparency::CLOSED, $tournament->getDecklistTransparency());
    }

    public function testStatusCanBeSetAndGet(): void
    {
        $tournament = new Tournament();

        $tournament->setStatus(TournamentStatus::PUBLISHED);
        $this->assertSame(TournamentStatus::PUBLISHED, $tournament->getStatus());

        $tournament->setStatus(TournamentStatus::ONGOING);
        $this->assertSame(TournamentStatus::ONGOING, $tournament->getStatus());

        $tournament->setStatus(TournamentStatus::COMPLETED);
        $this->assertSame(TournamentStatus::COMPLETED, $tournament->getStatus());
    }

    public function testSwissMatchFormatCanBeSetAndGet(): void
    {
        $tournament = new Tournament();

        $tournament->setSwissMatchFormat(MatchFormat::BO1);
        $this->assertSame(MatchFormat::BO1, $tournament->getSwissMatchFormat());

        $tournament->setSwissMatchFormat(MatchFormat::BO3);
        $this->assertSame(MatchFormat::BO3, $tournament->getSwissMatchFormat());
    }

    public function testEliminationMatchFormatCanBeSetAndGet(): void
    {
        $tournament = new Tournament();

        $tournament->setEliminationMatchFormat(MatchFormat::BO3);
        $this->assertSame(MatchFormat::BO3, $tournament->getEliminationMatchFormat());

        $tournament->setEliminationMatchFormat(null);
        $this->assertNull($tournament->getEliminationMatchFormat());
    }

    public function testEliminationMatchFormatIsNullByDefault(): void
    {
        $tournament = new Tournament();

        $this->assertNull($tournament->getEliminationMatchFormat());
    }

    public function testSwissRoundsCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setSwissRounds(5);

        $this->assertSame(5, $tournament->getSwissRounds());
    }

    public function testSwissRoundsIsNullByDefault(): void
    {
        $tournament = new Tournament();

        $this->assertNull($tournament->getSwissRounds());
    }

    public function testExpectedPlayersCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setExpectedPlayers(32);

        $this->assertSame(32, $tournament->getExpectedPlayers());
    }

    public function testTopCutSizeCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setTopCutSize(8);

        $this->assertSame(8, $tournament->getTopCutSize());
    }

    public function testRegistrationsClosedIsFalseByDefault(): void
    {
        $tournament = new Tournament();

        $this->assertFalse($tournament->isRegistrationsClosed());
    }

    public function testRegistrationsClosedCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $tournament->setRegistrationsClosed(true);

        $this->assertTrue($tournament->isRegistrationsClosed());
    }

    public function testIsEditableReturnsTrueWhenDraft(): void
    {
        $tournament = new Tournament();
        $tournament->setStatus(TournamentStatus::DRAFT);

        $this->assertTrue($tournament->isEditable());
    }

    public function testIsEditableReturnsFalseWhenPublished(): void
    {
        $tournament = new Tournament();
        $tournament->setStatus(TournamentStatus::PUBLISHED);

        $this->assertFalse($tournament->isEditable());
    }

    public function testIsEditableReturnsFalseWhenOngoing(): void
    {
        $tournament = new Tournament();
        $tournament->setStatus(TournamentStatus::ONGOING);

        $this->assertFalse($tournament->isEditable());
    }

    public function testIsEditableReturnsFalseWhenCompleted(): void
    {
        $tournament = new Tournament();
        $tournament->setStatus(TournamentStatus::COMPLETED);

        $this->assertFalse($tournament->isEditable());
    }

    public function testHasEliminationReturnsTrueForSingleElimination(): void
    {
        $tournament = new Tournament();
        $tournament->setStructure(TournamentStructure::SINGLE_ELIMINATION);

        $this->assertTrue($tournament->hasElimination());
    }

    public function testHasEliminationReturnsTrueForMixed(): void
    {
        $tournament = new Tournament();
        $tournament->setStructure(TournamentStructure::MIXED);

        $this->assertTrue($tournament->hasElimination());
    }

    public function testHasEliminationReturnsFalseForSwissOnly(): void
    {
        $tournament = new Tournament();
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);

        $this->assertFalse($tournament->hasElimination());
    }

    public function testHasSwissReturnsTrueForSwissOnly(): void
    {
        $tournament = new Tournament();
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);

        $this->assertTrue($tournament->hasSwiss());
    }

    public function testHasSwissReturnsTrueForMixed(): void
    {
        $tournament = new Tournament();
        $tournament->setStructure(TournamentStructure::MIXED);

        $this->assertTrue($tournament->hasSwiss());
    }

    public function testHasSwissReturnsFalseForSingleElimination(): void
    {
        $tournament = new Tournament();
        $tournament->setStructure(TournamentStructure::SINGLE_ELIMINATION);

        $this->assertFalse($tournament->hasSwiss());
    }

    public function testSetterMethodsReturnSelfForFluentInterface(): void
    {
        $tournament = new Tournament();
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setPseudo('Test');
        $user->setPassword('hash');

        $this->assertSame($tournament, $tournament->setName('Test'));
        $this->assertSame($tournament, $tournament->setOrganizer($user));
        $this->assertSame($tournament, $tournament->setDate(new \DateTimeImmutable()));
        $this->assertSame($tournament, $tournament->setTime(new \DateTimeImmutable()));
        $this->assertSame($tournament, $tournament->setLocation('Paris'));
        $this->assertSame($tournament, $tournament->setDescription('Desc'));
        $this->assertSame($tournament, $tournament->setEntryFee('10 EUR'));
        $this->assertSame($tournament, $tournament->setPrizes('Prizes'));
        $this->assertSame($tournament, $tournament->setAlteredGgLink('https://altered.gg'));
        $this->assertSame($tournament, $tournament->setFormat(TournamentFormat::CONSTRUCTED));
        $this->assertSame($tournament, $tournament->setStructure(TournamentStructure::SWISS_ONLY));
        $this->assertSame($tournament, $tournament->setVisibility(TournamentVisibility::PUBLIC));
        $this->assertSame($tournament, $tournament->setDecklistTransparency(DecklistTransparency::OPEN));
        $this->assertSame($tournament, $tournament->setStatus(TournamentStatus::DRAFT));
        $this->assertSame($tournament, $tournament->setSwissMatchFormat(MatchFormat::BO1));
        $this->assertSame($tournament, $tournament->setEliminationMatchFormat(MatchFormat::BO3));
        $this->assertSame($tournament, $tournament->setSwissRounds(5));
        $this->assertSame($tournament, $tournament->setExpectedPlayers(32));
        $this->assertSame($tournament, $tournament->setTopCutSize(8));
        $this->assertSame($tournament, $tournament->setRegistrationsClosed(false));
        $this->assertSame($tournament, $tournament->setUpdatedAt(new \DateTimeImmutable()));
    }

    public function testRegistrationsCollectionIsEmptyByDefault(): void
    {
        $tournament = new Tournament();

        $this->assertCount(0, $tournament->getRegistrations());
    }

    public function testAddRegistration(): void
    {
        $tournament = new Tournament();
        $player = $this->createMock(User::class);
        $registration = new Registration($tournament, $player);

        $tournament->addRegistration($registration);

        $this->assertCount(1, $tournament->getRegistrations());
        $this->assertTrue($tournament->getRegistrations()->contains($registration));
    }

    public function testAddRegistrationDoesNotAddDuplicates(): void
    {
        $tournament = new Tournament();
        $player = $this->createMock(User::class);
        $registration = new Registration($tournament, $player);

        $tournament->addRegistration($registration);
        $tournament->addRegistration($registration);

        $this->assertCount(1, $tournament->getRegistrations());
    }

    public function testRemoveRegistration(): void
    {
        $tournament = new Tournament();
        $player = $this->createMock(User::class);
        $registration = new Registration($tournament, $player);

        $tournament->addRegistration($registration);
        $tournament->removeRegistration($registration);

        $this->assertCount(0, $tournament->getRegistrations());
    }

    public function testGetRegistrationCount(): void
    {
        $tournament = new Tournament();

        $this->assertSame(0, $tournament->getRegistrationCount());

        $player1 = $this->createMock(User::class);
        $player2 = $this->createMock(User::class);
        $registration1 = new Registration($tournament, $player1);
        $registration2 = new Registration($tournament, $player2);

        $tournament->addRegistration($registration1);
        $this->assertSame(1, $tournament->getRegistrationCount());

        $tournament->addRegistration($registration2);
        $this->assertSame(2, $tournament->getRegistrationCount());
    }

    public function testIsPlayerRegistered(): void
    {
        $tournament = new Tournament();
        $player = $this->createMock(User::class);
        $otherPlayer = $this->createMock(User::class);
        $registration = new Registration($tournament, $player);

        $this->assertFalse($tournament->isPlayerRegistered($player));

        $tournament->addRegistration($registration);

        $this->assertTrue($tournament->isPlayerRegistered($player));
        $this->assertFalse($tournament->isPlayerRegistered($otherPlayer));
    }

    public function testIsOrganizerPlayingReturnsFalseWhenOrganizerNotRegistered(): void
    {
        $organizer = new User();
        $organizer->setEmail('organizer@test.com');
        $organizer->setPseudo('Organizer');
        $organizer->setPassword('hash');

        $tournament = new Tournament();
        $tournament->setOrganizer($organizer);

        $this->assertFalse($tournament->isOrganizerPlaying());
    }

    public function testIsOrganizerPlayingReturnsTrueWhenOrganizerRegistered(): void
    {
        $organizer = new User();
        $organizer->setEmail('organizer@test.com');
        $organizer->setPseudo('Organizer');
        $organizer->setPassword('hash');

        $tournament = new Tournament();
        $tournament->setOrganizer($organizer);

        $registration = new Registration($tournament, $organizer);
        $tournament->addRegistration($registration);

        $this->assertTrue($tournament->isOrganizerPlaying());
    }
}
