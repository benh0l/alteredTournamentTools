<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Enum\MatchStatus;
use App\Enum\RoundStatus;
use PHPUnit\Framework\TestCase;

final class RoundTest extends TestCase
{
    public function testIdIsNullBeforePersistence(): void
    {
        $round = new Round();

        $this->assertNull($round->getId());
    }

    public function testDefaultStatusIsPending(): void
    {
        $round = new Round();

        $this->assertSame(RoundStatus::PENDING, $round->getStatus());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $round = new Round();

        $this->assertInstanceOf(\DateTimeImmutable::class, $round->getCreatedAt());
    }

    public function testTournamentCanBeSetAndGet(): void
    {
        $tournament = new Tournament();
        $round = new Round();
        $round->setTournament($tournament);

        $this->assertSame($tournament, $round->getTournament());
    }

    public function testRoundNumberCanBeSetAndGet(): void
    {
        $round = new Round();
        $round->setRoundNumber(3);

        $this->assertSame(3, $round->getRoundNumber());
    }

    public function testStatusCanBeSetAndGet(): void
    {
        $round = new Round();
        $round->setStatus(RoundStatus::ONGOING);

        $this->assertSame(RoundStatus::ONGOING, $round->getStatus());
    }

    public function testStartedAtCanBeSetAndGet(): void
    {
        $round = new Round();
        $startedAt = new \DateTimeImmutable();
        $round->setStartedAt($startedAt);

        $this->assertSame($startedAt, $round->getStartedAt());
    }

    public function testCompletedAtCanBeSetAndGet(): void
    {
        $round = new Round();
        $completedAt = new \DateTimeImmutable();
        $round->setCompletedAt($completedAt);

        $this->assertSame($completedAt, $round->getCompletedAt());
    }

    public function testMatchesCollectionIsEmptyByDefault(): void
    {
        $round = new Round();

        $this->assertCount(0, $round->getMatches());
    }

    public function testAddMatch(): void
    {
        $round = new Round();
        $match = new TournamentMatch();

        $round->addMatch($match);

        $this->assertCount(1, $round->getMatches());
        $this->assertSame($round, $match->getRound());
    }

    public function testAddMatchDoesNotDuplicate(): void
    {
        $round = new Round();
        $match = new TournamentMatch();

        $round->addMatch($match);
        $round->addMatch($match);

        $this->assertCount(1, $round->getMatches());
    }

    public function testRemoveMatch(): void
    {
        $round = new Round();
        $match = new TournamentMatch();

        $round->addMatch($match);
        $round->removeMatch($match);

        $this->assertCount(0, $round->getMatches());
    }

    public function testIsPendingReturnsTrue(): void
    {
        $round = new Round();

        $this->assertTrue($round->isPending());
    }

    public function testIsOngoingReturnsTrueAfterStart(): void
    {
        $round = new Round();
        $round->start();

        $this->assertTrue($round->isOngoing());
        $this->assertFalse($round->isPending());
    }

    public function testIsCompletedReturnsTrueAfterComplete(): void
    {
        $round = new Round();
        $round->complete();

        $this->assertTrue($round->isCompleted());
        $this->assertFalse($round->isOngoing());
    }

    public function testStartSetsStartedAt(): void
    {
        $round = new Round();
        $round->start();

        $this->assertNotNull($round->getStartedAt());
        $this->assertSame(RoundStatus::ONGOING, $round->getStatus());
    }

    public function testCompleteSetsCompletedAt(): void
    {
        $round = new Round();
        $round->complete();

        $this->assertNotNull($round->getCompletedAt());
        $this->assertSame(RoundStatus::COMPLETED, $round->getStatus());
    }

    public function testGetCompletedMatchesCount(): void
    {
        $round = new Round();
        $player1 = $this->createMock(Registration::class);
        $player2 = $this->createMock(Registration::class);

        $match1 = new TournamentMatch();
        $match1->setPlayer1($player1);
        $match1->setPlayer2($player2);
        $match1->setTableNumber(1);
        $match1->setStatus(MatchStatus::COMPLETED);

        $match2 = new TournamentMatch();
        $match2->setPlayer1($player1);
        $match2->setPlayer2($player2);
        $match2->setTableNumber(2);
        $match2->setStatus(MatchStatus::PENDING);

        $round->addMatch($match1);
        $round->addMatch($match2);

        $this->assertSame(1, $round->getCompletedMatchesCount());
    }

    public function testAreAllMatchesCompletedReturnsFalseWhenPending(): void
    {
        $round = new Round();
        $player1 = $this->createMock(Registration::class);
        $player2 = $this->createMock(Registration::class);

        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::PENDING);

        $round->addMatch($match);

        $this->assertFalse($round->areAllMatchesCompleted());
    }

    public function testAreAllMatchesCompletedReturnsTrueWhenAllCompleted(): void
    {
        $round = new Round();
        $player1 = $this->createMock(Registration::class);
        $player2 = $this->createMock(Registration::class);

        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::COMPLETED);

        $round->addMatch($match);

        $this->assertTrue($round->areAllMatchesCompleted());
    }

    public function testHasDisputesReturnsTrueWhenDisputed(): void
    {
        $round = new Round();
        $player1 = $this->createMock(Registration::class);
        $player2 = $this->createMock(Registration::class);

        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::DISPUTE);

        $round->addMatch($match);

        $this->assertTrue($round->hasDisputes());
    }

    public function testSetterMethodsReturnSelfForFluentInterface(): void
    {
        $tournament = new Tournament();
        $round = new Round();

        $this->assertSame($round, $round->setTournament($tournament));
        $this->assertSame($round, $round->setRoundNumber(1));
        $this->assertSame($round, $round->setStatus(RoundStatus::PENDING));
        $this->assertSame($round, $round->setStartedAt(new \DateTimeImmutable()));
        $this->assertSame($round, $round->setCompletedAt(new \DateTimeImmutable()));
        $this->assertSame($round, $round->start());
        $this->assertSame($round, $round->complete());
    }
}
