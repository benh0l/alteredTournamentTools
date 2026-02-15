<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

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
use App\Service\StandingsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StandingsServiceTest extends TestCase
{
    private TournamentMatchRepository&MockObject $matchRepository;
    private StandingsService $service;

    protected function setUp(): void
    {
        $this->matchRepository = $this->createMock(TournamentMatchRepository::class);
        $this->service = new StandingsService($this->matchRepository);
    }

    public function testCalculateStandingsWithNoRegistrations(): void
    {
        $tournament = $this->createTournament();

        $result = $this->service->calculateStandings($tournament);

        $this->assertEmpty($result);
    }

    public function testCalculateStandingsWithNoMatches(): void
    {
        $user = $this->createUser('player1@test.com', 'Player1');
        $tournament = $this->createTournament();
        $registration = new Registration($tournament, $user);
        $tournament->addRegistration($registration);

        $this->matchRepository
            ->method('findByPlayer')
            ->willReturn([]);

        $this->matchRepository
            ->method('findOpponents')
            ->willReturn([]);

        $result = $this->service->calculateStandings($tournament);

        $this->assertCount(1, $result);
        $this->assertSame($registration, $result[0]['registration']);
        $this->assertSame(0, $result[0]['wins']);
        $this->assertSame(0, $result[0]['losses']);
        $this->assertSame(0, $result[0]['matchPoints']);
    }

    public function testCalculateStandingsWithWins(): void
    {
        $user1 = $this->createUser('player1@test.com', 'Player1');
        $user2 = $this->createUser('player2@test.com', 'Player2');
        $tournament = $this->createTournament();

        $reg1 = new Registration($tournament, $user1);
        $reg2 = new Registration($tournament, $user2);
        $tournament->addRegistration($reg1);
        $tournament->addRegistration($reg2);

        // Set registration IDs using reflection
        $this->setId($reg1, 1);
        $this->setId($reg2, 2);

        // Create a completed match where player1 wins
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($reg1);
        $match->setPlayer2($reg2);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::COMPLETED);
        $match->setResult(['winnerId' => 1, 'player1Score' => 2, 'player2Score' => 0]);

        $this->matchRepository
            ->method('findByPlayer')
            ->willReturnCallback(function ($reg) use ($match, $reg1, $reg2) {
                if ($reg->getId() === $reg1->getId() || $reg->getId() === $reg2->getId()) {
                    return [$match];
                }

                return [];
            });

        $this->matchRepository
            ->method('findOpponents')
            ->willReturn([]);

        $result = $this->service->calculateStandings($tournament);

        $this->assertCount(2, $result);

        // Player1 should be first (winner)
        $this->assertSame(1, $result[0]['wins']);
        $this->assertSame(0, $result[0]['losses']);
        $this->assertSame(3, $result[0]['matchPoints']);

        // Player2 should be second (loser)
        $this->assertSame(0, $result[1]['wins']);
        $this->assertSame(1, $result[1]['losses']);
        $this->assertSame(0, $result[1]['matchPoints']);
    }

    public function testCalculateStandingsSortsByMatchPoints(): void
    {
        $user1 = $this->createUser('player1@test.com', 'Player1');
        $user2 = $this->createUser('player2@test.com', 'Player2');
        $tournament = $this->createTournament();

        $reg1 = new Registration($tournament, $user1);
        $reg2 = new Registration($tournament, $user2);
        $tournament->addRegistration($reg1);
        $tournament->addRegistration($reg2);

        $this->setId($reg1, 1);
        $this->setId($reg2, 2);

        // Player 1: 0 wins
        // Player 2: 1 win (higher, should be first)

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($reg1);
        $match->setPlayer2($reg2);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::COMPLETED);
        $match->setResult(['winnerId' => 2, 'player1Score' => 0, 'player2Score' => 2]);

        $this->matchRepository
            ->method('findByPlayer')
            ->willReturn([$match]);

        $this->matchRepository
            ->method('findOpponents')
            ->willReturn([]);

        $result = $this->service->calculateStandings($tournament);

        // Player2 should be first
        $this->assertSame($reg2, $result[0]['registration']);
        $this->assertSame(3, $result[0]['matchPoints']);

        // Player1 should be second
        $this->assertSame($reg1, $result[1]['registration']);
        $this->assertSame(0, $result[1]['matchPoints']);
    }

    public function testCalculateStandingsWithByeMatch(): void
    {
        $user = $this->createUser('player1@test.com', 'Player1');
        $tournament = $this->createTournament();
        $registration = new Registration($tournament, $user);
        $tournament->addRegistration($registration);

        $this->setId($registration, 1);

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);

        $byeMatch = new TournamentMatch();
        $byeMatch->setRound($round);
        $byeMatch->setPlayer1($registration);
        $byeMatch->setPlayer2(null);
        $byeMatch->setTableNumber(1);
        $byeMatch->setIsBye(true);
        $byeMatch->setStatus(MatchStatus::COMPLETED);

        $this->matchRepository
            ->method('findByPlayer')
            ->willReturn([$byeMatch]);

        $this->matchRepository
            ->method('findOpponents')
            ->willReturn([]);

        $result = $this->service->calculateStandings($tournament);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['wins']); // BYE counts as win
        $this->assertSame(3, $result[0]['matchPoints']);
    }

    private function createUser(string $email, string $pseudo): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($pseudo);
        $user->setPassword('password');

        return $user;
    }

    private function createTournament(): Tournament
    {
        $organizer = $this->createUser('organizer@test.com', 'Organizer');

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

    private function setId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty(get_class($entity), 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
