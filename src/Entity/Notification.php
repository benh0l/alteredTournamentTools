<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Notification entity for tracking tournament reminders (FR64-FR67).
 *
 * Tracks the sending of notifications to prevent duplicates
 * and to maintain a history of sent reminders.
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notifications_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_notifications_tournament', columns: ['tournament_id'])]
#[ORM\Index(name: 'idx_notifications_type_status', columns: ['type', 'status'])]
#[ORM\UniqueConstraint(name: 'uniq_user_tournament_type', columns: ['user_id', 'tournament_id', 'type'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Tournament::class)]
    #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\Column(name: 'type', type: 'string', length: 20, enumType: NotificationType::class)]
    private NotificationType $type;

    #[ORM\Column(name: 'status', type: 'string', length: 10, enumType: NotificationStatus::class)]
    private NotificationStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    /**
     * Optional round number for ROUND_START notifications.
     */
    #[ORM\Column(name: 'round_number', type: 'smallint', nullable: true)]
    private ?int $roundNumber = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = NotificationStatus::PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function setTournament(Tournament $tournament): self
    {
        $this->tournament = $tournament;

        return $this;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function setType(NotificationType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function setStatus(NotificationStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getRoundNumber(): ?int
    {
        return $this->roundNumber;
    }

    public function setRoundNumber(?int $roundNumber): self
    {
        $this->roundNumber = $roundNumber;

        return $this;
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): self
    {
        $this->status = NotificationStatus::SENT;
        $this->sentAt = new \DateTimeImmutable();
        $this->errorMessage = null;

        return $this;
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->status = NotificationStatus::FAILED;
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === NotificationStatus::PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === NotificationStatus::SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === NotificationStatus::FAILED;
    }
}
