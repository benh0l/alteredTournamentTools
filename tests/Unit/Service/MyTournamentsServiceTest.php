<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\DecklistTransparency;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Repository\RegistrationRepository;
use App\Service\MyTournamentsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\MyTournamentsService
 */
final class MyTournamentsServiceTest extends TestCase
{
    private MyTournamentsService $service;
    private RegistrationRepository&MockObject $registrationRepository;

    protected function setUp(): void
    {
        $this->registrationRepository = $this->createMock(RegistrationRepository::class);
        $this->service = new MyTournamentsService($this->registrationRepository);
    }

    public function testGetGroupedTournamentsGroupsByStatus(): void
    {
        $player = $this->createPlayer();
        $organizer = $this->createPlayer();

        $upcomingTournament = $this->createTournament($organizer, TournamentStatus::PUBLISHED);
        $ongoingTournament = $this->createTournament($organizer, TournamentStatus::ONGOING);
        $completedTournament = $this->createTournament($organizer, TournamentStatus::COMPLETED);

        $upcomingReg = new Registration($upcomingTournament, $player);
        $ongoingReg = new Registration($ongoingTournament, $player);
        $completedReg = new Registration($completedTournament, $player);

        $this->registrationRepository
            ->expects($this->once())
            ->method('findByPlayerWithTournament')
            ->with($player)
            ->willReturn([$upcomingReg, $ongoingReg, $completedReg]);

        $result = $this->service->getGroupedTournamentsForPlayer($player);

        $this->assertCount(1, $result['upcoming']);
        $this->assertCount(1, $result['ongoing']);
        $this->assertCount(1, $result['completed']);

        $this->assertSame($upcomingReg, $result['upcoming'][0]);
        $this->assertSame($ongoingReg, $result['ongoing'][0]);
        $this->assertSame($completedReg, $result['completed'][0]);
    }

    public function testGetGroupedTournamentsExcludesDraftAndCancelled(): void
    {
        $player = $this->createPlayer();
        $organizer = $this->createPlayer();

        $draftTournament = $this->createTournament($organizer, TournamentStatus::DRAFT);
        $cancelledTournament = $this->createTournament($organizer, TournamentStatus::CANCELLED);

        $draftReg = new Registration($draftTournament, $player);
        $cancelledReg = new Registration($cancelledTournament, $player);

        $this->registrationRepository
            ->method('findByPlayerWithTournament')
            ->willReturn([$draftReg, $cancelledReg]);

        $result = $this->service->getGroupedTournamentsForPlayer($player);

        $this->assertCount(0, $result['upcoming']);
        $this->assertCount(0, $result['ongoing']);
        $this->assertCount(0, $result['completed']);
    }

    public function testGetGroupedTournamentsSortsUpcomingByDateAsc(): void
    {
        $player = $this->createPlayer();
        $organizer = $this->createPlayer();

        $tournament1 = $this->createTournament($organizer, TournamentStatus::PUBLISHED, new \DateTimeImmutable('+3 days'));
        $tournament2 = $this->createTournament($organizer, TournamentStatus::PUBLISHED, new \DateTimeImmutable('+1 day'));
        $tournament3 = $this->createTournament($organizer, TournamentStatus::PUBLISHED, new \DateTimeImmutable('+2 days'));

        $reg1 = new Registration($tournament1, $player);
        $reg2 = new Registration($tournament2, $player);
        $reg3 = new Registration($tournament3, $player);

        $this->registrationRepository
            ->method('findByPlayerWithTournament')
            ->willReturn([$reg1, $reg2, $reg3]);

        $result = $this->service->getGroupedTournamentsForPlayer($player);

        $this->assertCount(3, $result['upcoming']);
        // Should be sorted: +1 day, +2 days, +3 days
        $this->assertSame($reg2, $result['upcoming'][0]);
        $this->assertSame($reg3, $result['upcoming'][1]);
        $this->assertSame($reg1, $result['upcoming'][2]);
    }

    public function testGetGroupedTournamentsSortsCompletedByDateDesc(): void
    {
        $player = $this->createPlayer();
        $organizer = $this->createPlayer();

        $tournament1 = $this->createTournament($organizer, TournamentStatus::COMPLETED, new \DateTimeImmutable('-3 days'));
        $tournament2 = $this->createTournament($organizer, TournamentStatus::COMPLETED, new \DateTimeImmutable('-1 day'));
        $tournament3 = $this->createTournament($organizer, TournamentStatus::COMPLETED, new \DateTimeImmutable('-2 days'));

        $reg1 = new Registration($tournament1, $player);
        $reg2 = new Registration($tournament2, $player);
        $reg3 = new Registration($tournament3, $player);

        $this->registrationRepository
            ->method('findByPlayerWithTournament')
            ->willReturn([$reg1, $reg2, $reg3]);

        $result = $this->service->getGroupedTournamentsForPlayer($player);

        $this->assertCount(3, $result['completed']);
        // Should be sorted: -1 day, -2 days, -3 days (most recent first)
        $this->assertSame($reg2, $result['completed'][0]);
        $this->assertSame($reg3, $result['completed'][1]);
        $this->assertSame($reg1, $result['completed'][2]);
    }

    public function testGetGroupedTournamentsReturnsEmptyArraysWhenNoRegistrations(): void
    {
        $player = $this->createPlayer();

        $this->registrationRepository
            ->method('findByPlayerWithTournament')
            ->willReturn([]);

        $result = $this->service->getGroupedTournamentsForPlayer($player);

        $this->assertCount(0, $result['upcoming']);
        $this->assertCount(0, $result['ongoing']);
        $this->assertCount(0, $result['completed']);
    }

    public function testGetAllRegistrationsForPlayerDelegatesToRepository(): void
    {
        $player = $this->createPlayer();
        $expectedRegistrations = [];

        $this->registrationRepository
            ->expects($this->once())
            ->method('findByPlayerWithTournament')
            ->with($player)
            ->willReturn($expectedRegistrations);

        $result = $this->service->getAllRegistrationsForPlayer($player);

        $this->assertSame($expectedRegistrations, $result);
    }

    private function createPlayer(): User
    {
        $player = new User();
        $player->setEmail('player' . uniqid() . '@test.com');
        $player->setPseudo('Player' . uniqid());
        $player->setPassword('hashed');

        return $player;
    }

    private function createTournament(
        User $organizer,
        TournamentStatus $status,
        ?\DateTimeImmutable $date = null
    ): Tournament {
        $tournament = new Tournament();
        $tournament->setName('Test Tournament ' . uniqid());
        $tournament->setOrganizer($organizer);
        $tournament->setDate($date ?? new \DateTimeImmutable('+1 week'));
        $tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);
        $tournament->setDecklistTransparency(DecklistTransparency::OPEN);
        $tournament->setSwissMatchFormat(MatchFormat::BO3);
        $tournament->setStatus($status);

        return $tournament;
    }
}
