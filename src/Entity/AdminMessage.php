<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AdminMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity for tracking admin messages sent to organizers (FR71).
 */
#[ORM\Entity(repositoryClass: AdminMessageRepository::class)]
#[ORM\Table(name: 'admin_messages')]
#[ORM\Index(name: 'idx_admin_message_sender', columns: ['sender_id'])]
#[ORM\Index(name: 'idx_admin_message_sent_at', columns: ['sent_at'])]
class AdminMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $sender = null;

    #[ORM\Column(name: 'recipient_type', type: Types::STRING, length: 20)]
    private string $recipientType; // 'individual' or 'all_organizers'

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $recipientUser = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(name: 'recipient_count', type: Types::INTEGER)]
    private int $recipientCount = 1;

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getRecipientType(): string
    {
        return $this->recipientType;
    }

    public function setRecipientType(string $recipientType): self
    {
        $this->recipientType = $recipientType;

        return $this;
    }

    public function getRecipientUser(): ?User
    {
        return $this->recipientUser;
    }

    public function setRecipientUser(?User $recipientUser): self
    {
        $this->recipientUser = $recipientUser;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getRecipientCount(): int
    {
        return $this->recipientCount;
    }

    public function setRecipientCount(int $recipientCount): self
    {
        $this->recipientCount = $recipientCount;

        return $this;
    }

    public function isToAllOrganizers(): bool
    {
        return $this->recipientType === 'all_organizers';
    }

    public function getRecipientLabel(): string
    {
        if ($this->isToAllOrganizers()) {
            return "Tous les organisateurs ({$this->recipientCount})";
        }

        return $this->recipientUser?->getPseudo() ?? 'Inconnu';
    }
}
