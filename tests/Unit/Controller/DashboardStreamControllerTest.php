<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\Api\DashboardStreamController;
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
use Symfony\Contracts\Translation\TranslatorInterface;

final class DashboardStreamControllerTest extends TestCase
{
    private TournamentMatchRepository&MockObject $matchRepository;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->matchRepository = $this->createMock(TournamentMatchRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(fn ($key) => $key);
    }

    private function createController(): DashboardStreamController
    {
        return new DashboardStreamController(
            $this->matchRepository,
            $this->translator
        );
    }

    public function testBuildDashboardDataWithNoCurrentRound(): void
    {
        $tournament = $this->createTournament();

        $controller = $this->createController();

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildDashboardData');

        $result = $method->invoke($controller, $tournament);

        $this->assertNull($result['current_round']);
        $this->assertSame(0, $result['statistics']['total_matches']);
        $this->assertEmpty($result['matches']);
        $this->assertNull($result['timer']['started_at']);
        $this->assertSame(0, $result['timer']['elapsed_seconds']);
    }

    public function testBuildDashboardDataWithMatches(): void
    {
        $tournament = $this->createTournament();
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $round->startTimer();
        $tournament->addRound($round);

        $user = $this->createUser();
        $registration = new Registration($tournament, $user);

        // Create matches
        $match1 = new TournamentMatch();
        $match1->setRound($round);
        $match1->setPlayer1($registration);
        $match1->setPlayer2($registration);
        $match1->setTableNumber(1);
        $match1->setStatus(MatchStatus::COMPLETED);
        $match1->setResult(['winnerId' => 1, 'player1Score' => 2, 'player2Score' => 1]);
        $round->addMatch($match1);

        $match2 = new TournamentMatch();
        $match2->setRound($round);
        $match2->setPlayer1($registration);
        $match2->setPlayer2($registration);
        $match2->setTableNumber(2);
        $match2->setStatus(MatchStatus::ONGOING);
        $round->addMatch($match2);

        $controller = $this->createController();

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildDashboardData');

        $result = $method->invoke($controller, $tournament);

        $this->assertSame(1, $result['current_round']);
        $this->assertSame(2, $result['statistics']['total_matches']);
        $this->assertSame(1, $result['statistics']['completed_matches']);
        $this->assertSame(1, $result['statistics']['ongoing_matches']);
        $this->assertCount(2, $result['matches']);
        $this->assertNotNull($result['timer']['started_at']);
    }

    public function testBuildDashboardDataMatchStructure(): void
    {
        $tournament = $this->createTournament();
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $user = $this->createUser();
        $registration = new Registration($tournament, $user);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registration);
        $match->setPlayer2($registration);
        $match->setTableNumber(5);
        $match->setStatus(MatchStatus::PENDING);
        $round->addMatch($match);

        $controller = $this->createController();

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildDashboardData');

        $result = $method->invoke($controller, $tournament);

        $matchData = $result['matches'][0];
        $this->assertSame(5, $matchData['table_number']);
        $this->assertSame('TestPlayer', $matchData['player1']);
        $this->assertSame('pending', $matchData['status']);
        $this->assertFalse($matchData['is_bye']);
    }

    public function testBuildDashboardDataWithDisputedMatch(): void
    {
        $tournament = $this->createTournament();
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $user = $this->createUser();
        $registration = new Registration($tournament, $user);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registration);
        $match->setPlayer2($registration);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::DISPUTE);
        $round->addMatch($match);

        $controller = $this->createController();

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildDashboardData');

        $result = $method->invoke($controller, $tournament);

        $this->assertSame(1, $result['statistics']['disputed_matches']);
        $this->assertSame('dispute', $result['matches'][0]['status']);
    }

    public function testBuildDashboardDataWithByeMatch(): void
    {
        $tournament = $this->createTournament();
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $user = $this->createUser();
        $registration = new Registration($tournament, $user);

        $byeMatch = new TournamentMatch();
        $byeMatch->setRound($round);
        $byeMatch->setPlayer1($registration);
        $byeMatch->setPlayer2(null);
        $byeMatch->setTableNumber(1);
        $byeMatch->setIsBye(true);
        $byeMatch->setStatus(MatchStatus::COMPLETED);
        $round->addMatch($byeMatch);

        $controller = $this->createController();

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildDashboardData');

        $result = $method->invoke($controller, $tournament);

        $this->assertTrue($result['matches'][0]['is_bye']);
        $this->assertNull($result['matches'][0]['player2']);
        $this->assertSame(100.0, $result['statistics']['completion_percentage']);
    }

    private function createTournament(): Tournament
    {
        $organizer = $this->createUser();

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
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
