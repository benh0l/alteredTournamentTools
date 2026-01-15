<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\NotificationType;

/**
 * Async message for sending tournament reminder notifications.
 *
 * This message is dispatched to the Symfony Messenger bus
 * and processed asynchronously by SendTournamentReminderHandler.
 */
final readonly class SendTournamentReminderMessage
{
    public function __construct(
        private int $notificationId,
        private int $userId,
        private int $tournamentId,
        private NotificationType $type,
        private ?int $roundNumber = null,
    ) {
    }

    public function getNotificationId(): int
    {
        return $this->notificationId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getTournamentId(): int
    {
        return $this->tournamentId;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getRoundNumber(): ?int
    {
        return $this->roundNumber;
    }
}
