<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Notification;
use App\Entity\Registration;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Message\SendTournamentReminderMessage;
use App\Repository\NotificationRepository;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use App\Service\NotificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tests for NotificationService (FR64-FR67).
 */
final class NotificationServiceTest extends TestCase
{
    private NotificationRepository&MockObject $notificationRepository;
    private TournamentRepository&MockObject $tournamentRepository;
    private RegistrationRepository&MockObject $registrationRepository;
    private MessageBusInterface&MockObject $messageBus;
    private NotificationService $service;

    private User $player;
    private User $organizer;
    private Tournament $tournament;
    private Registration $registration;

    protected function setUp(): void
    {
        $this->notificationRepository = $this->createMock(NotificationRepository::class);
        $this->tournamentRepository = $this->createMock(TournamentRepository::class);
        $this->registrationRepository = $this->createMock(RegistrationRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new NotificationService(
            $this->notificationRepository,
            $this->tournamentRepository,
            $this->registrationRepository,
            $this->messageBus,
            new NullLogger()
        );

        $this->setupFixtures();
    }

    private function setupFixtures(): void
    {
        $this->organizer = new User();
        $this->organizer->setEmail('organizer@test.com');
        $this->organizer->setPseudo('Organizer');
        $this->organizer->setPassword('password');

        $this->player = new User();
        $this->player->setEmail('player@test.com');
        $this->player->setPseudo('Player');
        $this->player->setPassword('password');

        $this->tournament = new Tournament();
        $this->tournament->setName('Test Tournament');
        $this->tournament->setOrganizer($this->organizer);
        $this->tournament->setDate(new \DateTimeImmutable('tomorrow'));
        $this->tournament->setTime(new \DateTimeImmutable('14:00'));
        $this->tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $this->tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $this->tournament->setVisibility(TournamentVisibility::PUBLIC);
        $this->tournament->setStatus(TournamentStatus::PUBLISHED);

        $this->registration = new Registration($this->tournament, $this->player);
    }

    public function testCreateNotification(): void
    {
        $this->notificationRepository
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->isInstanceOf(Notification::class),
                true
            );

        $notification = $this->service->createNotification(
            $this->player,
            $this->tournament,
            NotificationType::D_MINUS_1
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->player, $notification->getUser());
        $this->assertEquals($this->tournament, $notification->getTournament());
        $this->assertEquals(NotificationType::D_MINUS_1, $notification->getType());
        $this->assertEquals(NotificationStatus::PENDING, $notification->getStatus());
    }

    public function testCreateNotificationWithRoundNumber(): void
    {
        $this->notificationRepository
            ->expects($this->once())
            ->method('save');

        $notification = $this->service->createNotification(
            $this->player,
            $this->tournament,
            NotificationType::ROUND_START,
            3
        );

        $this->assertEquals(3, $notification->getRoundNumber());
    }

    public function testDispatchNotification(): void
    {
        $notification = new Notification();
        $notification->setUser($this->player);
        $notification->setTournament($this->tournament);
        $notification->setType(NotificationType::D_MINUS_1);

        // Use reflection to set the ID since we can't set it directly
        $reflection = new \ReflectionClass($notification);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($notification, 1);

        // Also set IDs for user and tournament
        $userReflection = new \ReflectionClass($this->player);
        $userIdProperty = $userReflection->getProperty('id');
        $userIdProperty->setAccessible(true);
        $userIdProperty->setValue($this->player, 10);

        $tournamentReflection = new \ReflectionClass($this->tournament);
        $tournamentIdProperty = $tournamentReflection->getProperty('id');
        $tournamentIdProperty->setAccessible(true);
        $tournamentIdProperty->setValue($this->tournament, 20);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendTournamentReminderMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->dispatchNotification($notification);
    }

    public function testQueueRoundStartNotifications(): void
    {
        // Set IDs for the fixtures
        $tournamentReflection = new \ReflectionClass($this->tournament);
        $tournamentIdProperty = $tournamentReflection->getProperty('id');
        $tournamentIdProperty->setAccessible(true);
        $tournamentIdProperty->setValue($this->tournament, 20);

        $playerReflection = new \ReflectionClass($this->player);
        $playerIdProperty = $playerReflection->getProperty('id');
        $playerIdProperty->setAccessible(true);
        $playerIdProperty->setValue($this->player, 10);

        $this->registrationRepository
            ->method('findBy')
            ->with(['tournament' => $this->tournament])
            ->willReturn([$this->registration]);

        $this->notificationRepository
            ->method('hasBeenSent')
            ->willReturn(false);

        $notificationId = 1;
        $this->notificationRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Notification $notification) use (&$notificationId): void {
                $reflection = new \ReflectionClass($notification);
                $idProperty = $reflection->getProperty('id');
                $idProperty->setAccessible(true);
                $idProperty->setValue($notification, $notificationId++);
            });

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $stats = $this->service->queueRoundStartNotifications($this->tournament, 1);

        $this->assertEquals(1, $stats['queued']);
        $this->assertEquals(0, $stats['skipped']);
    }

    public function testQueueRoundStartNotificationsSkipsAlreadySent(): void
    {
        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$this->registration]);

        $this->notificationRepository
            ->method('hasBeenSent')
            ->willReturn(true);

        $this->notificationRepository
            ->expects($this->never())
            ->method('save');

        $stats = $this->service->queueRoundStartNotifications($this->tournament, 1);

        $this->assertEquals(0, $stats['queued']);
        $this->assertEquals(1, $stats['skipped']);
    }

    public function testGetStatsReturnsEmptyForNewTournament(): void
    {
        $this->notificationRepository
            ->method('findByTournament')
            ->willReturn([]);

        $stats = $this->service->getStats($this->tournament);

        foreach (NotificationType::cases() as $type) {
            $this->assertEquals(0, $stats[$type->value][NotificationStatus::PENDING->value]);
            $this->assertEquals(0, $stats[$type->value][NotificationStatus::SENT->value]);
            $this->assertEquals(0, $stats[$type->value][NotificationStatus::FAILED->value]);
        }
    }

    public function testGetStatsCountsNotifications(): void
    {
        $notification1 = new Notification();
        $notification1->setUser($this->player);
        $notification1->setTournament($this->tournament);
        $notification1->setType(NotificationType::D_MINUS_1);
        $notification1->markAsSent();

        $notification2 = new Notification();
        $notification2->setUser($this->organizer);
        $notification2->setTournament($this->tournament);
        $notification2->setType(NotificationType::D_MINUS_1);
        $notification2->setStatus(NotificationStatus::PENDING);

        $this->notificationRepository
            ->method('findByTournament')
            ->willReturn([$notification1, $notification2]);

        $stats = $this->service->getStats($this->tournament);

        $this->assertEquals(1, $stats[NotificationType::D_MINUS_1->value][NotificationStatus::SENT->value]);
        $this->assertEquals(1, $stats[NotificationType::D_MINUS_1->value][NotificationStatus::PENDING->value]);
    }
}
