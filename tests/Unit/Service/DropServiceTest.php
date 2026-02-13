<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchFormat;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Event\PlayerDroppedEvent;
use App\Exception\DropException;
use App\Repository\TournamentMatchRepository;
use App\Service\DropService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \App\Service\DropService
 */
final class DropServiceTest extends TestCase
{
    private DropService $service;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&TournamentMatchRepository $matchRepository;
    private MockObject&EventDispatcherInterface $eventDispatcher;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->matchRepository = $this->createMock(TournamentMatchRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new DropService(
            $this->entityManager,
            $this->matchRepository,
            $this->eventDispatcher,
            $this->logger
        );
    }

    public function testDropPlayerSuccessfully(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();

        $this->matchRepository->expects($this->once())
            ->method('findActiveMatchForRegistration')
            ->with($registration)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (PlayerDroppedEvent $event) use ($registration) {
                return $event->getRegistration() === $registration
                    && $event->isByOrganizer() === false
                    && $event->getReason() === '';
            }));

        $this->service->dropPlayer($registration);

        $this->assertTrue($registration->isDropped());
    }

    public function testDropPlayerWithReason(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();
        $reason = 'Personal emergency';

        $this->matchRepository->expects($this->once())
            ->method('findActiveMatchForRegistration')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (PlayerDroppedEvent $event) use ($reason) {
                return $event->getReason() === $reason;
            }));

        $this->service->dropPlayer($registration, false, $reason);

        $this->assertTrue($registration->isDropped());
    }

    public function testDropPlayerByOrganizer(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();

        $this->matchRepository->expects($this->once())
            ->method('findActiveMatchForRegistration')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (PlayerDroppedEvent $event) {
                return $event->isByOrganizer() === true;
            }));

        $this->service->dropPlayer($registration, false, '', true);

        $this->assertTrue($registration->isDropped());
    }

    public function testDropPlayerThrowsExceptionWhenTournamentNotOngoing(): void
    {
        $tournament = $this->createMock(Tournament::class);
        $tournament->method('getStatus')->willReturn(TournamentStatus::PUBLISHED);

        $player = $this->createMock(User::class);
        $registration = new Registration($tournament, $player);

        $this->expectException(DropException::class);
        $this->expectExceptionMessage('Les joueurs ne peuvent abandonner que pendant un tournoi en cours.');

        $this->service->dropPlayer($registration);
    }

    public function testDropPlayerThrowsExceptionWhenAlreadyDropped(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();
        $registration->drop();

        $this->expectException(DropException::class);
        $this->expectExceptionMessage('Ce joueur a deja abandonne le tournoi.');

        $this->service->dropPlayer($registration);
    }

    public function testDropPlayerThrowsExceptionWithActiveMatchAndNoForfeit(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();

        $activeMatch = $this->createMock(TournamentMatch::class);
        $activeMatch->method('getId')->willReturn(123);

        $this->matchRepository->expects($this->once())
            ->method('findActiveMatchForRegistration')
            ->willReturn($activeMatch);

        $this->expectException(DropException::class);
        $this->expectExceptionMessage('Le joueur a un match en cours. Utilisez l\'option de forfait pour continuer.');

        $this->service->dropPlayer($registration, false);
    }

    public function testDropPlayerWithForfeitActiveMatch(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();

        // Create opponent
        $opponentPlayer = $this->createMock(User::class);
        $opponentPlayer->method('getId')->willReturn(2);
        $opponentPlayer->method('getPseudo')->willReturn('Opponent');

        $opponent = $this->createMock(Registration::class);
        $opponent->method('getId')->willReturn(20);
        $opponent->method('getPlayer')->willReturn($opponentPlayer);

        // Create tournament with BO1 format
        $tournament = $registration->getTournament();

        $round = $this->createMock(Round::class);
        $round->method('getTournament')->willReturn($tournament);

        // Create active match
        $activeMatch = new TournamentMatch();
        $activeMatch->setRound($round);
        $activeMatch->setPlayer1($registration);
        $activeMatch->setPlayer2($opponent);
        $activeMatch->setTableNumber(1);
        $activeMatch->setStatus(MatchStatus::ONGOING);

        $this->matchRepository->expects($this->once())
            ->method('findActiveMatchForRegistration')
            ->willReturn($activeMatch);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch');

        $this->service->dropPlayer($registration, true);

        $this->assertTrue($registration->isDropped());
        $this->assertEquals(MatchStatus::COMPLETED, $activeMatch->getStatus());
        $this->assertNotNull($activeMatch->getCompletedAt());

        $result = $activeMatch->getResult();
        $this->assertNotNull($result);
        $this->assertEquals(20, $result['winnerId']);
        $this->assertEquals(0, $result['player1Score']);
        $this->assertEquals(1, $result['player2Score']); // BO1 score
        $this->assertTrue($result['forfeit']);
        $this->assertEquals('drop', $result['forfeitReason']);
    }

    public function testUndropPlayerSuccessfully(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();
        $registration->drop();

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->undropPlayer($registration);

        $this->assertFalse($registration->isDropped());
    }

    public function testUndropPlayerThrowsExceptionWhenNotDropped(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();

        $this->expectException(DropException::class);
        $this->expectExceptionMessage('Ce joueur n\'a pas abandonne le tournoi.');

        $this->service->undropPlayer($registration);
    }

    public function testHasActiveMatchReturnsTrue(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();

        $activeMatch = $this->createMock(TournamentMatch::class);

        $this->matchRepository->expects($this->once())
            ->method('findActiveMatchForRegistration')
            ->willReturn($activeMatch);

        $this->assertTrue($this->service->hasActiveMatch($registration));
    }

    public function testHasActiveMatchReturnsFalse(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();

        $this->matchRepository->expects($this->once())
            ->method('findActiveMatchForRegistration')
            ->willReturn(null);

        $this->assertFalse($this->service->hasActiveMatch($registration));
    }

    public function testGetActiveMatch(): void
    {
        $registration = $this->createRegistrationWithOngoingTournament();

        $activeMatch = $this->createMock(TournamentMatch::class);

        $this->matchRepository->expects($this->once())
            ->method('findActiveMatchForRegistration')
            ->willReturn($activeMatch);

        $this->assertSame($activeMatch, $this->service->getActiveMatch($registration));
    }

    /**
     * Creates a registration with an ongoing tournament for testing.
     */
    private function createRegistrationWithOngoingTournament(): Registration
    {
        $tournament = $this->createMock(Tournament::class);
        $tournament->method('getStatus')->willReturn(TournamentStatus::ONGOING);
        $tournament->method('getId')->willReturn(1);
        $tournament->method('getName')->willReturn('Test Tournament');
        $tournament->method('getMatchFormatForRound')->willReturn(MatchFormat::BO1);

        $player = $this->createMock(User::class);
        $player->method('getId')->willReturn(1);
        $player->method('getPseudo')->willReturn('TestPlayer');

        return new Registration($tournament, $player);
    }
}
