<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentStatus;
use App\Exception\AlreadyRegisteredException;
use App\Exception\RegistrationClosedException;
use App\Exception\TournamentNotOpenException;
use App\Repository\RegistrationRepository;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \App\Service\RegistrationService
 */
final class RegistrationServiceTest extends TestCase
{
    private RegistrationService $service;
    private EntityManagerInterface&MockObject $entityManager;
    private RegistrationRepository&MockObject $registrationRepository;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registrationRepository = $this->createMock(RegistrationRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new RegistrationService(
            $this->entityManager,
            $this->registrationRepository,
            $this->logger
        );
    }

    public function testRegisterPlayerSuccessfully(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::PUBLISHED);

        $this->registrationRepository
            ->expects($this->once())
            ->method('isPlayerRegistered')
            ->with($tournament, $player)
            ->willReturn(false);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Registration::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $registration = $this->service->registerPlayerToTournament(
            $player,
            $tournament,
            'https://altered.gg/deck/abc123'
        );

        $this->assertInstanceOf(Registration::class, $registration);
        $this->assertSame($tournament, $registration->getTournament());
        $this->assertSame($player, $registration->getPlayer());
        $this->assertSame('https://altered.gg/deck/abc123', $registration->getDecklistUrl());
    }

    public function testRegisterPlayerWithoutDecklist(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::PUBLISHED);

        $this->registrationRepository
            ->method('isPlayerRegistered')
            ->willReturn(false);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $registration = $this->service->registerPlayerToTournament(
            $player,
            $tournament,
            null
        );

        $this->assertNull($registration->getDecklistUrl());
    }

    public function testRegisterPlayerThrowsExceptionWhenTournamentNotPublished(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::DRAFT);

        $this->expectException(TournamentNotOpenException::class);

        $this->service->registerPlayerToTournament($player, $tournament);
    }

    public function testRegisterPlayerThrowsExceptionWhenTournamentOngoing(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::ONGOING);

        $this->expectException(TournamentNotOpenException::class);

        $this->service->registerPlayerToTournament($player, $tournament);
    }

    public function testRegisterPlayerThrowsExceptionWhenRegistrationsClosed(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::PUBLISHED);
        $tournament->setRegistrationsClosed(true);

        $this->expectException(RegistrationClosedException::class);

        $this->service->registerPlayerToTournament($player, $tournament);
    }

    public function testRegisterPlayerThrowsExceptionWhenAlreadyRegistered(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::PUBLISHED);

        $this->registrationRepository
            ->method('isPlayerRegistered')
            ->with($tournament, $player)
            ->willReturn(true);

        $this->expectException(AlreadyRegisteredException::class);

        $this->service->registerPlayerToTournament($player, $tournament);
    }

    public function testIsPlayerRegisteredDelegatesToRepository(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::PUBLISHED);

        $this->registrationRepository
            ->expects($this->once())
            ->method('isPlayerRegistered')
            ->with($tournament, $player)
            ->willReturn(true);

        $result = $this->service->isPlayerRegistered($player, $tournament);

        $this->assertTrue($result);
    }

    public function testUnregisterPlayerSuccessfully(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::PUBLISHED);
        $registration = new Registration($tournament, $player);

        $this->registrationRepository
            ->expects($this->once())
            ->method('findOneByTournamentAndPlayer')
            ->with($tournament, $player)
            ->willReturn($registration);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($registration);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->unregisterPlayer($player, $tournament);
    }

    public function testUnregisterPlayerThrowsExceptionWhenNotRegistered(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::PUBLISHED);

        $this->registrationRepository
            ->method('findOneByTournamentAndPlayer')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le joueur n\'est pas inscrit');

        $this->service->unregisterPlayer($player, $tournament);
    }

    public function testUnregisterPlayerThrowsExceptionWhenTournamentNotPublished(): void
    {
        $player = $this->createPlayer(1, 'player@test.com', 'Player1');
        $tournament = $this->createTournament(1, TournamentStatus::ONGOING);
        $registration = new Registration($tournament, $player);

        $this->registrationRepository
            ->method('findOneByTournamentAndPlayer')
            ->willReturn($registration);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Impossible de se desinscrire');

        $this->service->unregisterPlayer($player, $tournament);
    }

    private function createPlayer(int $id, string $email, string $pseudo): User
    {
        $player = new User();
        $player->setEmail($email);
        $player->setPseudo($pseudo);
        $player->setPassword('hashed');

        // Use reflection to set the ID
        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($player, $id);

        return $player;
    }

    private function createTournament(int $id, TournamentStatus $status): Tournament
    {
        $organizer = $this->createPlayer(100, 'org@test.com', 'Organizer');

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable('+1 week'));
        $tournament->setFormat(\App\Enum\TournamentFormat::CONSTRUCTED);
        $tournament->setStructure(\App\Enum\TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(\App\Enum\TournamentVisibility::PUBLIC);
        $tournament->setDecklistTransparency(\App\Enum\DecklistTransparency::OPEN);
        $tournament->setSwissMatchFormat(\App\Enum\MatchFormat::BO3);
        $tournament->setStatus($status);

        // Use reflection to set the ID
        $reflection = new \ReflectionProperty(Tournament::class, 'id');
        $reflection->setValue($tournament, $id);

        return $tournament;
    }
}
