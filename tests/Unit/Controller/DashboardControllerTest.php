<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\DashboardController;
use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Repository\TournamentMatchRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DashboardControllerTest extends TestCase
{
    private TournamentMatchRepository&MockObject $matchRepository;

    protected function setUp(): void
    {
        $this->matchRepository = $this->createMock(TournamentMatchRepository::class);
    }

    public function testCalculateRoundStatisticsWithNoCurrentRound(): void
    {
        $tournament = $this->createTournament();

        $controller = new DashboardController($this->matchRepository);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('calculateRoundStatistics');

        $result = $method->invoke($controller, $tournament);

        $this->assertSame(0, $result['total_matches']);
        $this->assertSame(0, $result['completed_matches']);
        $this->assertSame(0, $result['ongoing_matches']);
        $this->assertSame(0, $result['pending_matches']);
        $this->assertSame(0, $result['disputed_matches']);
        $this->assertSame(0, $result['bye_matches']);
        $this->assertSame(0.0, $result['completion_percentage']);
    }

    public function testCalculateRoundStatisticsWithMatches(): void
    {
        $tournament = $this->createTournament();
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        // Create matches with different statuses
        $user = $this->createUser();
        $registration = new Registration($tournament, $user);

        // Completed match
        $match1 = new TournamentMatch();
        $match1->setRound($round);
        $match1->setPlayer1($registration);
        $match1->setPlayer2($registration);
        $match1->setTableNumber(1);
        $match1->setStatus(MatchStatus::COMPLETED);
        $round->addMatch($match1);

        // Ongoing match
        $match2 = new TournamentMatch();
        $match2->setRound($round);
        $match2->setPlayer1($registration);
        $match2->setPlayer2($registration);
        $match2->setTableNumber(2);
        $match2->setStatus(MatchStatus::ONGOING);
        $round->addMatch($match2);

        // Pending match
        $match3 = new TournamentMatch();
        $match3->setRound($round);
        $match3->setPlayer1($registration);
        $match3->setPlayer2($registration);
        $match3->setTableNumber(3);
        $match3->setStatus(MatchStatus::PENDING);
        $round->addMatch($match3);

        // Disputed match
        $match4 = new TournamentMatch();
        $match4->setRound($round);
        $match4->setPlayer1($registration);
        $match4->setPlayer2($registration);
        $match4->setTableNumber(4);
        $match4->setStatus(MatchStatus::DISPUTE);
        $round->addMatch($match4);

        $controller = new DashboardController($this->matchRepository);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('calculateRoundStatistics');

        $result = $method->invoke($controller, $tournament);

        $this->assertSame(4, $result['total_matches']);
        $this->assertSame(1, $result['completed_matches']);
        $this->assertSame(1, $result['ongoing_matches']);
        $this->assertSame(1, $result['pending_matches']);
        $this->assertSame(1, $result['disputed_matches']);
        $this->assertSame(0, $result['bye_matches']);
        $this->assertSame(25.0, $result['completion_percentage']);
    }

    public function testCalculateRoundStatisticsWithByeMatch(): void
    {
        $tournament = $this->createTournament();
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $user = $this->createUser();
        $registration = new Registration($tournament, $user);

        // BYE match
        $byeMatch = new TournamentMatch();
        $byeMatch->setRound($round);
        $byeMatch->setPlayer1($registration);
        $byeMatch->setPlayer2(null);
        $byeMatch->setTableNumber(1);
        $byeMatch->setIsBye(true);
        $byeMatch->setStatus(MatchStatus::COMPLETED);
        $round->addMatch($byeMatch);

        // Regular completed match
        $regularMatch = new TournamentMatch();
        $regularMatch->setRound($round);
        $regularMatch->setPlayer1($registration);
        $regularMatch->setPlayer2($registration);
        $regularMatch->setTableNumber(2);
        $regularMatch->setStatus(MatchStatus::COMPLETED);
        $round->addMatch($regularMatch);

        $controller = new DashboardController($this->matchRepository);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('calculateRoundStatistics');

        $result = $method->invoke($controller, $tournament);

        $this->assertSame(2, $result['total_matches']);
        $this->assertSame(2, $result['completed_matches']); // BYE counts as completed
        $this->assertSame(1, $result['bye_matches']);
        $this->assertSame(100.0, $result['completion_percentage']); // Only 1 playable match, which is completed
    }

    public function testCalculateRoundStatisticsAllByeMatches(): void
    {
        $tournament = $this->createTournament();
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $user = $this->createUser();
        $registration = new Registration($tournament, $user);

        // Only BYE match
        $byeMatch = new TournamentMatch();
        $byeMatch->setRound($round);
        $byeMatch->setPlayer1($registration);
        $byeMatch->setPlayer2(null);
        $byeMatch->setTableNumber(1);
        $byeMatch->setIsBye(true);
        $byeMatch->setStatus(MatchStatus::COMPLETED);
        $round->addMatch($byeMatch);

        $controller = new DashboardController($this->matchRepository);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('calculateRoundStatistics');

        $result = $method->invoke($controller, $tournament);

        $this->assertSame(1, $result['total_matches']);
        $this->assertSame(1, $result['bye_matches']);
        $this->assertSame(100.0, $result['completion_percentage']); // Edge case: all BYE = 100%
    }

    private function createTournament(): Tournament
    {
        $organizer = $this->createUser();

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);
        $tournament->setStatus(TournamentStatus::ONGOING);

        return $tournament;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPseudo('TestPlayer');
        $user->setPassword('password');

        return $user;
    }
}
