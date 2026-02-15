<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Pairing;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Service\Pairing\PlayerStandings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlayerStandingsTest extends TestCase
{
    private Registration&MockObject $registration;
    private int $mockIdCounter = 1;

    protected function setUp(): void
    {
        $this->mockIdCounter = 1;
        $this->registration = $this->createMockRegistrationWithId($this->mockIdCounter++);
    }

    public function testGetRegistration(): void
    {
        $standings = new PlayerStandings($this->registration);

        $this->assertSame($this->registration, $standings->getRegistration());
    }

    public function testInitialMatchPoints(): void
    {
        $standings = new PlayerStandings($this->registration);

        $this->assertSame(0, $standings->getMatchPoints());
    }

    public function testAddWin(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->addWin();

        $this->assertSame(1, $standings->getWins());
        $this->assertSame(3, $standings->getMatchPoints());
    }

    public function testAddMultipleWins(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->addWin()->addWin()->addWin();

        $this->assertSame(3, $standings->getWins());
        $this->assertSame(9, $standings->getMatchPoints());
    }

    public function testAddLoss(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->addLoss();

        $this->assertSame(1, $standings->getLosses());
        $this->assertSame(0, $standings->getMatchPoints());
    }

    public function testAddDraw(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->addDraw();

        $this->assertSame(1, $standings->getDraws());
        $this->assertSame(1, $standings->getMatchPoints());
    }

    public function testAddBye(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->addBye();

        $this->assertSame(1, $standings->getByeCount());
        $this->assertTrue($standings->hasReceivedBye());
        $this->assertSame(3, $standings->getMatchPoints()); // BYE counts as win
        $this->assertSame(1, $standings->getWins()); // BYE is a win
    }

    public function testHasReceivedByeIsFalseByDefault(): void
    {
        $standings = new PlayerStandings($this->registration);

        $this->assertFalse($standings->hasReceivedBye());
    }

    public function testOpponentManagement(): void
    {
        $opponent = $this->createMockRegistration();
        $standings = new PlayerStandings($this->registration);

        $standings->addOpponent($opponent);

        $this->assertCount(1, $standings->getOpponents());
        $this->assertTrue($standings->hasPlayedAgainst($opponent));
    }

    public function testHasNotPlayedAgainstNewPlayer(): void
    {
        $opponent1 = $this->createMockRegistration();
        $opponent2 = $this->createMockRegistration();
        $standings = new PlayerStandings($this->registration);

        $standings->addOpponent($opponent1);

        $this->assertTrue($standings->hasPlayedAgainst($opponent1));
        $this->assertFalse($standings->hasPlayedAgainst($opponent2));
    }

    /**
     * Test that hasPlayedAgainst works correctly when comparing different object instances
     * with the same ID (simulates Doctrine returning different instances for same entity).
     *
     * This is the key test for the rematch bug fix - ensures comparison is by ID, not by reference.
     */
    public function testHasPlayedAgainstComparesById(): void
    {
        // Create two different mock objects representing the same player (same ID)
        $opponentInstance1 = $this->createMockRegistrationWithId(42);
        $opponentInstance2 = $this->createMockRegistrationWithId(42);
        $differentOpponent = $this->createMockRegistrationWithId(99);

        $standings = new PlayerStandings($this->registration);

        // Add the first instance as opponent
        $standings->addOpponent($opponentInstance1);

        // Both instances should be recognized as the same player (same ID)
        $this->assertTrue($standings->hasPlayedAgainst($opponentInstance1));
        $this->assertTrue($standings->hasPlayedAgainst($opponentInstance2), 'Different object instance with same ID should be recognized as same opponent');

        // But a truly different player should not be recognized
        $this->assertFalse($standings->hasPlayedAgainst($differentOpponent));
    }

    public function testMatchesPlayed(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->addWin()->addLoss()->addWin();

        $this->assertSame(3, $standings->getMatchesPlayed());
    }

    public function testMatchesPlayedExcludesByes(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->addWin()->addBye()->addWin();

        // 3 total (2 wins + 1 bye-win), but bye doesn't count as match played
        $this->assertSame(2, $standings->getMatchesPlayed());
    }

    public function testMatchWinPercentage(): void
    {
        $standings = new PlayerStandings($this->registration);

        // 2 wins, 1 loss = 6 points / (3 matches * 3 max) = 66.67%
        $standings->addWin()->addWin()->addLoss();

        $this->assertEqualsWithDelta(0.667, $standings->getMatchWinPercentage(), 0.01);
    }

    public function testMatchWinPercentageMinimum(): void
    {
        $standings = new PlayerStandings($this->registration);

        // 0 wins, 3 losses = 0% but minimum is 33%
        $standings->addLoss()->addLoss()->addLoss();

        $this->assertSame(0.33, $standings->getMatchWinPercentage());
    }

    public function testMatchWinPercentageNoMatches(): void
    {
        $standings = new PlayerStandings($this->registration);

        // No matches played, returns minimum
        $this->assertSame(0.33, $standings->getMatchWinPercentage());
    }

    public function testSetMatchPoints(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->setMatchPoints(15);

        $this->assertSame(15, $standings->getMatchPoints());
    }

    public function testSetOpponentMatchWinPercentage(): void
    {
        $standings = new PlayerStandings($this->registration);

        $standings->setOpponentMatchWinPercentage(0.55);

        $this->assertSame(0.55, $standings->getOpponentMatchWinPercentage());
    }

    public function testFluentInterface(): void
    {
        $standings = new PlayerStandings($this->registration);

        $result = $standings->addWin()->addLoss()->addDraw()->addBye();

        $this->assertSame($standings, $result);
    }

    private function createMockRegistration(): Registration
    {
        return $this->createMockRegistrationWithId($this->mockIdCounter++);
    }

    private function createMockRegistrationWithId(int $id): Registration
    {
        $registration = $this->createMock(Registration::class);
        $registration->method('getId')->willReturn($id);

        return $registration;
    }
}
