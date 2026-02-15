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
use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;
use App\Service\PlayerMatchService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlayerMatchServiceTest extends TestCase
{
    private RegistrationRepository&MockObject $registrationRepository;
    private TournamentMatchRepository&MockObject $matchRepository;
    private PlayerMatchService $service;

    protected function setUp(): void
    {
        $this->registrationRepository = $this->createMock(RegistrationRepository::class);
        $this->matchRepository = $this->createMock(TournamentMatchRepository::class);

        $this->service = new PlayerMatchService(
            $this->registrationRepository,
            $this->matchRepository
        );
    }

    public function testFindCurrentMatchReturnsNullWhenNoRegistrations(): void
    {
        $user = $this->createUser();

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([]);

        $result = $this->service->findCurrentMatch($user);

        $this->assertNull($result);
    }

    public function testFindCurrentMatchReturnsNullWhenTournamentNotOngoing(): void
    {
        $user = $this->createUser();
        $tournament = $this->createTournament(TournamentStatus::PUBLISHED);
        $registration = new Registration($tournament, $user);

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$registration]);

        $result = $this->service->findCurrentMatch($user);

        $this->assertNull($result);
    }

    public function testFindCurrentMatchReturnsMatchWhenPlayerInOngoingMatch(): void
    {
        $user = $this->createUser();
        $tournament = $this->createTournament(TournamentStatus::ONGOING);
        $registration = new Registration($tournament, $user);

        // Create round with match
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registration);
        $match->setPlayer2($registration);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::ONGOING);
        $round->addMatch($match);

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$registration]);

        $result = $this->service->findCurrentMatch($user);

        $this->assertSame($match, $result);
    }

    public function testFindCurrentMatchReturnsNullWhenMatchCompleted(): void
    {
        $user = $this->createUser();
        $tournament = $this->createTournament(TournamentStatus::ONGOING);
        $registration = new Registration($tournament, $user);

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registration);
        $match->setPlayer2($registration);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::COMPLETED);
        $round->addMatch($match);

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$registration]);

        $result = $this->service->findCurrentMatch($user);

        $this->assertNull($result);
    }

    public function testGetUpcomingTournamentRegistrations(): void
    {
        $user = $this->createUser();
        $upcomingTournament = $this->createTournament(TournamentStatus::PUBLISHED);
        $ongoingTournament = $this->createTournament(TournamentStatus::ONGOING);

        $upcomingReg = new Registration($upcomingTournament, $user);
        $ongoingReg = new Registration($ongoingTournament, $user);

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$upcomingReg, $ongoingReg]);

        $result = $this->service->getUpcomingTournamentRegistrations($user);

        $this->assertCount(1, $result);
        $this->assertSame($upcomingReg, $result[0]);
    }

    public function testGetOngoingTournamentRegistrations(): void
    {
        $user = $this->createUser();
        $upcomingTournament = $this->createTournament(TournamentStatus::PUBLISHED);
        $ongoingTournament = $this->createTournament(TournamentStatus::ONGOING);

        $upcomingReg = new Registration($upcomingTournament, $user);
        $ongoingReg = new Registration($ongoingTournament, $user);

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$upcomingReg, $ongoingReg]);

        $result = $this->service->getOngoingTournamentRegistrations($user);

        $this->assertCount(1, $result);
        $this->assertSame($ongoingReg, $result[0]);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPseudo('TestPlayer');
        $user->setPassword('password');

        return $user;
    }

    private function createTournament(TournamentStatus $status): Tournament
    {
        $organizer = $this->createUser();
        $organizer->setEmail('organizer@example.com');

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable('+1 day'));
        $tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);
        $tournament->setStatus($status);

        return $tournament;
    }
}
