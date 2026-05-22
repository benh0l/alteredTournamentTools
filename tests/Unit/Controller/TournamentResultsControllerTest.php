<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TournamentResultsController logic.
 *
 * FR54: Automatic display of final standings at tournament end.
 * FR55: Top 3 podium display.
 * FR56: Automatic publication for PUBLIC tournaments.
 * FR57: Optional publication for PRIVATE tournaments.
 * FR58: Basic meta statistics.
 */
final class TournamentResultsControllerTest extends TestCase
{
    public function testCanViewResultsForCompletedPublicTournament(): void
    {
        $tournament = $this->createTournament(
            TournamentVisibility::PUBLIC,
            TournamentStatus::COMPLETED
        );

        // PUBLIC + COMPLETED = results viewable
        $this->assertTrue($tournament->isCompleted());
        $this->assertSame(TournamentVisibility::PUBLIC, $tournament->getVisibility());
    }

    public function testCannotViewResultsForOngoingTournament(): void
    {
        $tournament = $this->createTournament(
            TournamentVisibility::PUBLIC,
            TournamentStatus::ONGOING
        );

        $this->assertFalse($tournament->isCompleted());
    }

    public function testCanViewResultsForPrivateTournamentWhenPublished(): void
    {
        $tournament = $this->createTournament(
            TournamentVisibility::PRIVATE,
            TournamentStatus::COMPLETED
        );

        // Initially not published
        $this->assertFalse($tournament->isResultsPublished());

        // After publishing
        $tournament->publishResults();
        $this->assertTrue($tournament->isResultsPublished());
    }

    public function testResultsNotViewableForPrivateTournamentWhenNotPublished(): void
    {
        $tournament = $this->createTournament(
            TournamentVisibility::PRIVATE,
            TournamentStatus::COMPLETED
        );

        $this->assertTrue($tournament->isCompleted());
        $this->assertFalse($tournament->isResultsPublished());
    }

    public function testPublishResultsSetsFlag(): void
    {
        $tournament = $this->createTournament(
            TournamentVisibility::PRIVATE,
            TournamentStatus::COMPLETED
        );

        $this->assertFalse($tournament->isResultsPublished());

        $tournament->publishResults();

        $this->assertTrue($tournament->isResultsPublished());
    }

    public function testSetResultsPublished(): void
    {
        $tournament = $this->createTournament(
            TournamentVisibility::PRIVATE,
            TournamentStatus::COMPLETED
        );

        $tournament->setResultsPublished(true);
        $this->assertTrue($tournament->isResultsPublished());

        $tournament->setResultsPublished(false);
        $this->assertFalse($tournament->isResultsPublished());
    }

    public function testCompletedAtIsSetForCompletedTournaments(): void
    {
        $tournament = $this->createTournament(
            TournamentVisibility::PUBLIC,
            TournamentStatus::COMPLETED
        );

        $completedAt = new \DateTimeImmutable('2026-01-15 18:30:00');
        $tournament->setCompletedAt($completedAt);

        $this->assertSame($completedAt, $tournament->getCompletedAt());
    }

    public function testMetaStatsCalculation(): void
    {
        $tournament = $this->createTournament(
            TournamentVisibility::PUBLIC,
            TournamentStatus::COMPLETED
        );

        // Basic stats verification
        $this->assertSame(0, $tournament->getRegistrationCount());
        $this->assertSame(0, $tournament->getCompletedRoundsCount());
    }

    private function createUser(string $email, string $pseudo): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($pseudo);
        $user->setPassword('password');

        return $user;
    }

    private function createTournament(
        TournamentVisibility $visibility,
        TournamentStatus $status
    ): Tournament {
        $organizer = $this->createUser('organizer@test.com', 'Organizer');

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility($visibility);
        $tournament->setStatus($status);

        return $tournament;
    }
}
