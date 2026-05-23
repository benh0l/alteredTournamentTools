<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Service\CalendarService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CalendarService (FR64).
 */
final class CalendarServiceTest extends TestCase
{
    private CalendarService $service;
    private Tournament $tournament;
    private User $organizer;

    protected function setUp(): void
    {
        $this->service = new CalendarService();

        $this->organizer = new User();
        $this->organizer->setEmail('organizer@test.com');
        $this->organizer->setPseudo('Organizer');
        $this->organizer->setPassword('password');

        $this->tournament = new Tournament();
        $this->tournament->setName('Test Tournament');
        $this->tournament->setOrganizer($this->organizer);
        $this->tournament->setDate(new \DateTimeImmutable('2026-02-15'));
        $this->tournament->setTime(new \DateTimeImmutable('14:00'));
        $this->tournament->setLocation('Game Store Paris, 10 Rue du Test, 75001 Paris');
        $this->tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $this->tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $this->tournament->setVisibility(TournamentVisibility::PUBLIC);
        $this->tournament->setEntryFee('5 EUR');
    }

    public function testGenerateICSReturnsValidFormat(): void
    {
        $ics = $this->service->generateICS($this->tournament);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('VERSION:2.0', $ics);
        $this->assertStringContainsString('PRODID:-//AlteredTournamentTools//FR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }

    public function testGenerateICSContainsTournamentName(): void
    {
        $ics = $this->service->generateICS($this->tournament);

        $this->assertStringContainsString('SUMMARY:Test Tournament', $ics);
    }

    public function testGenerateICSContainsLocation(): void
    {
        $ics = $this->service->generateICS($this->tournament);

        $this->assertStringContainsString('LOCATION:', $ics);
        $this->assertStringContainsString('Game Store Paris', $ics);
    }

    public function testGenerateICSContainsCorrectDateTime(): void
    {
        $ics = $this->service->generateICS($this->tournament);

        // Date 2026-02-15 at 14:00 -> DTSTART:20260215T140000
        $this->assertStringContainsString('DTSTART:20260215T140000', $ics);
        // End time 6 hours later: 20:00
        $this->assertStringContainsString('DTEND:20260215T200000', $ics);
    }

    public function testGenerateICSContainsDescription(): void
    {
        $ics = $this->service->generateICS($this->tournament);

        $this->assertStringContainsString('DESCRIPTION:', $ics);
        // Labels are now translation keys
        $this->assertStringContainsString('Format: enum.tournament_format.constructed_standard', $ics);
    }

    public function testGenerateICSContainsAlarm(): void
    {
        $ics = $this->service->generateICS($this->tournament);

        $this->assertStringContainsString('BEGIN:VALARM', $ics);
        $this->assertStringContainsString('TRIGGER:-PT1H', $ics);
        $this->assertStringContainsString('END:VALARM', $ics);
    }

    public function testGenerateICSHasUniqueUID(): void
    {
        $ics = $this->service->generateICS($this->tournament);

        // UID should contain the tournament ID
        $this->assertMatchesRegularExpression('/UID:tournament-\d+@alteredtournament\.tools/', $ics);
    }

    public function testGenerateICSWithoutLocation(): void
    {
        $this->tournament->setLocation(null);

        $ics = $this->service->generateICS($this->tournament);

        $this->assertStringNotContainsString('LOCATION:', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
    }

    public function testGenerateICSDefaultTimeWhenNotSet(): void
    {
        $this->tournament->setTime(null);

        $ics = $this->service->generateICS($this->tournament);

        // Default time is 14:00
        $this->assertStringContainsString('DTSTART:20260215T140000', $ics);
    }

    public function testGenerateICSEscapesSpecialCharacters(): void
    {
        $this->tournament->setName('Test, with; special: chars');

        $ics = $this->service->generateICS($this->tournament);

        // Semicolons and commas should be escaped
        $this->assertStringContainsString('Test\\, with\\; special: chars', $ics);
    }

    public function testGetFilenameReturnsValidFormat(): void
    {
        $filename = $this->service->getFilename($this->tournament);

        $this->assertStringStartsWith('tournoi-', $filename);
        $this->assertStringEndsWith('.ics', $filename);
        $this->assertStringContainsString('2026-02-15', $filename);
    }

    public function testGetFilenameSlugifiesName(): void
    {
        $this->tournament->setName('Tournoi d\'Ete a Paris');

        $filename = $this->service->getFilename($this->tournament);

        // Should be slugified: lowercase, no accents, hyphens instead of spaces
        $this->assertMatchesRegularExpression('/tournoi-[a-z0-9-]+-2026-02-15\.ics/', $filename);
    }
}
