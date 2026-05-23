<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\MatchSubmission;
use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Enum\MatchFormat;
use App\Enum\MatchStatus;
use App\Enum\TournamentStructure;
use PHPUnit\Framework\TestCase;

final class TournamentMatchTest extends TestCase
{
    public function testIdIsNullBeforePersistence(): void
    {
        $match = new TournamentMatch();

        $this->assertNull($match->getId());
    }

    public function testDefaultStatusIsPending(): void
    {
        $match = new TournamentMatch();

        $this->assertSame(MatchStatus::PENDING, $match->getStatus());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $match = new TournamentMatch();

        $this->assertInstanceOf(\DateTimeImmutable::class, $match->getCreatedAt());
    }

    public function testIsByeIsFalseByDefault(): void
    {
        $match = new TournamentMatch();

        $this->assertFalse($match->isBye());
    }

    public function testRoundCanBeSetAndGet(): void
    {
        $round = new Round();
        $match = new TournamentMatch();
        $match->setRound($round);

        $this->assertSame($round, $match->getRound());
    }

    public function testPlayer1CanBeSetAndGet(): void
    {
        $player = $this->createMock(Registration::class);
        $match = new TournamentMatch();
        $match->setPlayer1($player);

        $this->assertSame($player, $match->getPlayer1());
    }

    public function testPlayer2CanBeSetAndGetAndIsNullable(): void
    {
        $player = $this->createMock(Registration::class);
        $match = new TournamentMatch();

        $this->assertNull($match->getPlayer2());

        $match->setPlayer2($player);
        $this->assertSame($player, $match->getPlayer2());

        $match->setPlayer2(null);
        $this->assertNull($match->getPlayer2());
    }

    public function testTableNumberCanBeSetAndGet(): void
    {
        $match = new TournamentMatch();
        $match->setTableNumber(5);

        $this->assertSame(5, $match->getTableNumber());
    }

    public function testResultCanBeSetAndGet(): void
    {
        $match = new TournamentMatch();
        $result = ['winnerId' => 123, 'player1Score' => 2, 'player2Score' => 1];
        $match->setResult($result);

        $this->assertSame($result, $match->getResult());
    }

    public function testResultIsNullByDefault(): void
    {
        $match = new TournamentMatch();

        $this->assertNull($match->getResult());
    }

    public function testCompletedAtIsNullByDefault(): void
    {
        $match = new TournamentMatch();

        $this->assertNull($match->getCompletedAt());
    }

    public function testCompletedAtCanBeSetAndGet(): void
    {
        $match = new TournamentMatch();
        $completedAt = new \DateTimeImmutable();
        $match->setCompletedAt($completedAt);

        $this->assertSame($completedAt, $match->getCompletedAt());
    }

    public function testIsByeMatchReturnsTrueWhenIsByeFlagIsTrue(): void
    {
        $match = new TournamentMatch();
        $match->setIsBye(true);

        $this->assertTrue($match->isByeMatch());
    }

    public function testIsByeMatchReturnsTrueWhenPlayer2IsNull(): void
    {
        $player1 = $this->createMock(Registration::class);
        $match = new TournamentMatch();
        $match->setPlayer1($player1);

        $this->assertTrue($match->isByeMatch());
    }

    public function testIsByeMatchReturnsFalseWhenBothPlayersExist(): void
    {
        $player1 = $this->createMock(Registration::class);
        $player2 = $this->createMock(Registration::class);
        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);

        $this->assertFalse($match->isByeMatch());
    }

    public function testIsPending(): void
    {
        $match = new TournamentMatch();

        $this->assertTrue($match->isPending());

        $match->setStatus(MatchStatus::ONGOING);
        $this->assertFalse($match->isPending());
    }

    public function testIsOngoing(): void
    {
        $match = new TournamentMatch();
        $match->setStatus(MatchStatus::ONGOING);

        $this->assertTrue($match->isOngoing());
    }

    public function testIsCompleted(): void
    {
        $match = new TournamentMatch();
        $match->setStatus(MatchStatus::COMPLETED);

        $this->assertTrue($match->isCompleted());
    }

    public function testIsDisputed(): void
    {
        $match = new TournamentMatch();
        $match->setStatus(MatchStatus::DISPUTE);

        $this->assertTrue($match->isDisputed());
    }

    public function testAssignBye(): void
    {
        $player1 = $this->createMock(Registration::class);
        $player1->method('getId')->willReturn(123);

        // Setup tournament and round for assignBye to work
        $tournament = new Tournament();
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setSwissMatchFormat(MatchFormat::BO3);

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player1);
        $match->setTableNumber(1);
        $match->assignBye();

        $this->assertTrue($match->isBye());
        $this->assertTrue($match->isByeMatch());
        $this->assertNull($match->getPlayer2());
        $this->assertSame(MatchStatus::COMPLETED, $match->getStatus());
        $this->assertNotNull($match->getCompletedAt());

        $result = $match->getResult();
        $this->assertNotNull($result);
        $this->assertSame(123, $result['winnerId']);
        $this->assertSame(2, $result['player1Score']); // BO3 format = 2 points
        $this->assertSame(0, $result['player2Score']);
        $this->assertTrue($result['isBye']);
    }

    public function testGetWinnerReturnsPlayer1WhenWinner(): void
    {
        $player1 = $this->createMock(Registration::class);
        $player1->method('getId')->willReturn(123);

        $player2 = $this->createMock(Registration::class);
        $player2->method('getId')->willReturn(456);

        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber(1);
        $match->setResult(['winnerId' => 123, 'player1Score' => 2, 'player2Score' => 0]);

        $this->assertSame($player1, $match->getWinner());
    }

    public function testGetWinnerReturnsPlayer2WhenWinner(): void
    {
        $player1 = $this->createMock(Registration::class);
        $player1->method('getId')->willReturn(123);

        $player2 = $this->createMock(Registration::class);
        $player2->method('getId')->willReturn(456);

        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber(1);
        $match->setResult(['winnerId' => 456, 'player1Score' => 1, 'player2Score' => 2]);

        $this->assertSame($player2, $match->getWinner());
    }

    public function testGetWinnerReturnsNullWhenNoResult(): void
    {
        $match = new TournamentMatch();

        $this->assertNull($match->getWinner());
    }

    public function testGetLoserReturnsPlayer2WhenPlayer1Wins(): void
    {
        $player1 = $this->createMock(Registration::class);
        $player1->method('getId')->willReturn(123);

        $player2 = $this->createMock(Registration::class);
        $player2->method('getId')->willReturn(456);

        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber(1);
        $match->setResult(['winnerId' => 123, 'player1Score' => 2, 'player2Score' => 0]);

        $this->assertSame($player2, $match->getLoser());
    }

    public function testGetLoserReturnsNullForByeMatch(): void
    {
        $player1 = $this->createMock(Registration::class);
        $player1->method('getId')->willReturn(123);

        // Setup tournament and round for assignBye to work
        $tournament = new Tournament();
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setSwissMatchFormat(MatchFormat::BO3);

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player1);
        $match->setTableNumber(1);
        $match->assignBye();

        $this->assertNull($match->getLoser());
    }

    public function testGetPlayer1Score(): void
    {
        $match = new TournamentMatch();
        $match->setResult(['player1Score' => 2, 'player2Score' => 1]);

        $this->assertSame(2, $match->getPlayer1Score());
    }

    public function testGetPlayer2Score(): void
    {
        $match = new TournamentMatch();
        $match->setResult(['player1Score' => 2, 'player2Score' => 1]);

        $this->assertSame(1, $match->getPlayer2Score());
    }

    public function testHasPlayer(): void
    {
        $player1 = $this->createMock(Registration::class);
        $player2 = $this->createMock(Registration::class);
        $otherPlayer = $this->createMock(Registration::class);

        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber(1);

        $this->assertTrue($match->hasPlayer($player1));
        $this->assertTrue($match->hasPlayer($player2));
        $this->assertFalse($match->hasPlayer($otherPlayer));
    }

    public function testGetOpponent(): void
    {
        $player1 = $this->createMock(Registration::class);
        $player2 = $this->createMock(Registration::class);

        $match = new TournamentMatch();
        $match->setPlayer1($player1);
        $match->setPlayer2($player2);
        $match->setTableNumber(1);

        $this->assertSame($player2, $match->getOpponent($player1));
        $this->assertSame($player1, $match->getOpponent($player2));
    }

    public function testComplete(): void
    {
        $match = new TournamentMatch();
        $result = ['winnerId' => 123, 'player1Score' => 2, 'player2Score' => 1];

        $match->complete($result);

        $this->assertSame($result, $match->getResult());
        $this->assertSame(MatchStatus::COMPLETED, $match->getStatus());
        $this->assertNotNull($match->getCompletedAt());
    }

    public function testDispute(): void
    {
        $match = new TournamentMatch();
        $match->dispute();

        $this->assertSame(MatchStatus::DISPUTE, $match->getStatus());
    }

    public function testSetterMethodsReturnSelfForFluentInterface(): void
    {
        $round = new Round();
        $player = $this->createMock(Registration::class);
        $match = new TournamentMatch();

        $this->assertSame($match, $match->setRound($round));
        $this->assertSame($match, $match->setPlayer1($player));
        $this->assertSame($match, $match->setPlayer2($player));
        $this->assertSame($match, $match->setTableNumber(1));
        $this->assertSame($match, $match->setResult([]));
        $this->assertSame($match, $match->setStatus(MatchStatus::PENDING));
        $this->assertSame($match, $match->setIsBye(false));
        $this->assertSame($match, $match->setCompletedAt(new \DateTimeImmutable()));
        $this->assertSame($match, $match->complete([]));
        $this->assertSame($match, $match->dispute());
    }

    // =========================================================================
    // Dispute History Tests
    // =========================================================================

    public function testDisputeHistoryIsNullByDefault(): void
    {
        $match = new TournamentMatch();

        $this->assertNull($match->getDisputeHistory());
    }

    public function testAddDisputeHistoryEntry(): void
    {
        $match = new TournamentMatch();

        $match->addDisputeHistoryEntry('created', ['reason' => 'Conflicting results']);

        $history = $match->getDisputeHistory();
        $this->assertNotNull($history);
        $this->assertCount(1, $history);
        $this->assertSame('created', $history[0]['type']);
        $this->assertSame(['reason' => 'Conflicting results'], $history[0]['data']);
        $this->assertArrayHasKey('timestamp', $history[0]);
    }

    public function testAddMultipleDisputeHistoryEntries(): void
    {
        $match = new TournamentMatch();

        $match->addDisputeHistoryEntry('submission', ['player' => 1, 'winner' => 1]);
        $match->addDisputeHistoryEntry('submission', ['player' => 2, 'winner' => 2]);
        $match->addDisputeHistoryEntry('created', ['reason' => 'Conflicting']);
        $match->addDisputeHistoryEntry('resolved', ['resolvedBy' => 'organizer', 'winner' => 1]);

        $history = $match->getDisputeHistory();
        $this->assertCount(4, $history);
        $this->assertSame('submission', $history[0]['type']);
        $this->assertSame('submission', $history[1]['type']);
        $this->assertSame('created', $history[2]['type']);
        $this->assertSame('resolved', $history[3]['type']);
    }

    public function testClearDisputeHistory(): void
    {
        $match = new TournamentMatch();
        $match->addDisputeHistoryEntry('created', ['reason' => 'Test']);

        $match->clearDisputeHistory();

        $this->assertNull($match->getDisputeHistory());
    }

    // =========================================================================
    // Submissions Collection Tests
    // =========================================================================

    public function testSubmissionsIsEmptyByDefault(): void
    {
        $match = new TournamentMatch();

        $this->assertCount(0, $match->getSubmissions());
        $this->assertSame(0, $match->getSubmissionCount());
    }

    public function testAddSubmission(): void
    {
        $match = new TournamentMatch();
        $match->setTableNumber(1);
        $round = new Round();
        $match->setRound($round);

        $submission = new MatchSubmission();
        $match->addSubmission($submission);

        $this->assertCount(1, $match->getSubmissions());
        $this->assertSame(1, $match->getSubmissionCount());
        $this->assertSame($match, $submission->getMatch());
    }

    public function testAddSubmissionDoesNotDuplicate(): void
    {
        $match = new TournamentMatch();
        $match->setTableNumber(1);
        $round = new Round();
        $match->setRound($round);

        $submission = new MatchSubmission();
        $match->addSubmission($submission);
        $match->addSubmission($submission);

        $this->assertCount(1, $match->getSubmissions());
    }

    public function testRemoveSubmission(): void
    {
        $match = new TournamentMatch();
        $match->setTableNumber(1);
        $round = new Round();
        $match->setRound($round);

        $submission = new MatchSubmission();
        $match->addSubmission($submission);
        $match->removeSubmission($submission);

        $this->assertCount(0, $match->getSubmissions());
    }

    public function testClearSubmissions(): void
    {
        $match = new TournamentMatch();
        $match->setTableNumber(1);
        $round = new Round();
        $match->setRound($round);

        $submission1 = new MatchSubmission();
        $submission2 = new MatchSubmission();
        $match->addSubmission($submission1);
        $match->addSubmission($submission2);

        $match->clearSubmissions();

        $this->assertCount(0, $match->getSubmissions());
    }

    public function testHasBothSubmissionsReturnsFalse(): void
    {
        $match = new TournamentMatch();
        $match->setTableNumber(1);
        $round = new Round();
        $match->setRound($round);

        $this->assertFalse($match->hasBothSubmissions());

        $submission1 = new MatchSubmission();
        $match->addSubmission($submission1);
        $this->assertFalse($match->hasBothSubmissions());
    }

    public function testHasBothSubmissionsReturnsTrue(): void
    {
        $match = new TournamentMatch();
        $match->setTableNumber(1);
        $round = new Round();
        $match->setRound($round);

        $submission1 = new MatchSubmission();
        $submission2 = new MatchSubmission();
        $match->addSubmission($submission1);
        $match->addSubmission($submission2);

        $this->assertTrue($match->hasBothSubmissions());
    }
}
