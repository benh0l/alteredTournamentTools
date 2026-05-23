<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentStatus;
use App\Repository\TournamentRepository;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class TournamentServiceTest extends TestCase
{
    private TournamentService $service;
    private MockObject $entityManager;
    private MockObject $repository;
    private MockObject $logger;
    private MockObject $tokenStorage;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(TournamentRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->service = new TournamentService(
            $this->entityManager,
            $this->repository,
            $this->logger,
            $this->tokenStorage
        );
    }

    public function testCreateTournamentSetsOrganizerAndStatus(): void
    {
        $tournament = new Tournament();
        $tournament->setName('Test Tournament');

        $user = new User();
        $user->setEmail('organizer@test.com');
        $user->setPseudo('Organizer');
        $user->setPassword('hashed');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        // Logger may be called multiple times (once for role grant, once for tournament creation)
        $this->logger->expects($this->atLeastOnce())->method('info');

        $result = $this->service->createTournament($tournament, $user);

        $this->assertSame($user, $result->getOrganizer());
        $this->assertSame(TournamentStatus::DRAFT, $result->getStatus());
    }

    public function testUpdateTournamentThrowsExceptionWhenNotDraft(): void
    {
        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setStatus(TournamentStatus::PUBLISHED);
        // Add a round so PUBLISHED tournament is not editable
        $round = new \App\Entity\Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $tournament->addRound($round);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot edit tournament that is not in DRAFT status');

        $this->service->updateTournament($tournament);
    }

    public function testUpdateTournamentFlushesWhenDraft(): void
    {
        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setStatus(TournamentStatus::DRAFT);

        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->updateTournament($tournament);

        $this->assertSame($tournament, $result);
    }

    public function testPublishTournamentChangesStatusToPublished(): void
    {
        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setStatus(TournamentStatus::DRAFT);

        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->publishTournament($tournament);

        $this->assertSame(TournamentStatus::PUBLISHED, $result->getStatus());
    }

    public function testPublishTournamentThrowsExceptionWhenNotDraft(): void
    {
        $tournament = new Tournament();
        $tournament->setStatus(TournamentStatus::ONGOING);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only DRAFT tournaments can be published');

        $this->service->publishTournament($tournament);
    }

    /**
     * @dataProvider swissRoundsDataProvider
     */
    public function testSuggestSwissRoundsCalculatesCorrectly(int $players, bool $isBo1, int $expectedRounds): void
    {
        $result = $this->service->suggestSwissRounds($players, $isBo1);

        $this->assertSame($expectedRounds, $result);
    }

    /**
     * @return array<string, array{int, bool, int}>
     */
    public static function swissRoundsDataProvider(): array
    {
        return [
            '8 players BO3' => [8, false, 3],
            '8 players BO1' => [8, true, 4],
            '16 players BO3' => [16, false, 4],
            '16 players BO1' => [16, true, 5],
            '32 players BO3' => [32, false, 5],
            '32 players BO1' => [32, true, 6],
            '64 players BO3' => [64, false, 6],
            '64 players BO1' => [64, true, 7],
            '128 players BO3' => [128, false, 7],
            '128 players BO1' => [128, true, 8],
            '4 players BO3' => [4, false, 2],
            '4 players BO1' => [4, true, 3],
            'less than 4 players' => [3, false, 1],
        ];
    }

    public function testGetTournamentsByOrganizerCallsRepository(): void
    {
        $user = new User();
        $tournaments = [new Tournament(), new Tournament()];

        $this->repository->expects($this->once())
            ->method('findByOrganizer')
            ->with($user)
            ->willReturn($tournaments);

        $result = $this->service->getTournamentsByOrganizer($user);

        $this->assertSame($tournaments, $result);
    }

    public function testGetUpcomingPublicTournamentsCallsRepository(): void
    {
        $tournaments = [new Tournament()];

        $this->repository->expects($this->once())
            ->method('findUpcomingPublicTournaments')
            ->willReturn($tournaments);

        $result = $this->service->getUpcomingPublicTournaments();

        $this->assertSame($tournaments, $result);
    }
}
