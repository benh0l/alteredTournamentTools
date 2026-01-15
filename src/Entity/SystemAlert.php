<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AlertSeverity;
use App\Enum\AlertType;
use App\Repository\SystemAlertRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * System health alert entity (FR75).
 */
#[ORM\Entity(repositoryClass: SystemAlertRepository::class)]
#[ORM\Table(name: 'system_alerts')]
#[ORM\Index(name: 'idx_system_alerts_type', columns: ['type'])]
#[ORM\Index(name: 'idx_system_alerts_severity', columns: ['severity'])]
#[ORM\Index(name: 'idx_system_alerts_created', columns: ['created_at'])]
class SystemAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'type', type: 'string', length: 50, enumType: AlertType::class)]
    private AlertType $type;

    #[ORM\Column(name: 'severity', type: 'string', length: 20, enumType: AlertSeverity::class)]
    private AlertSeverity $severity;

    #[ORM\Column(name: 'message', type: 'string', length: 255)]
    private string $message;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'details', type: 'json', nullable: true)]
    private ?array $details = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'acknowledged_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'acknowledged_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $acknowledgedBy = null;

    /**
     * Hash to identify duplicate alerts.
     */
    #[ORM\Column(name: 'alert_hash', type: 'string', length: 64, nullable: true)]
    private ?string $alertHash = null;

    public function __construct(AlertType $type, AlertSeverity $severity, string $message)
    {
        $this->type = $type;
        $this->severity = $severity;
        $this->message = $message;
        $this->createdAt = new \DateTimeImmutable();
        $this->alertHash = $this->generateHash();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): AlertType
    {
        return $this->type;
    }

    public function getSeverity(): AlertSeverity
    {
        return $this->severity;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public function setDetails(?array $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function getAcknowledgedBy(): ?User
    {
        return $this->acknowledgedBy;
    }

    public function acknowledge(User $admin): self
    {
        $this->acknowledgedAt = new \DateTimeImmutable();
        $this->acknowledgedBy = $admin;

        return $this;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledgedAt !== null;
    }

    public function isActive(): bool
    {
        return !$this->isAcknowledged();
    }

    public function getAlertHash(): ?string
    {
        return $this->alertHash;
    }

    private function generateHash(): string
    {
        return hash('sha256', $this->type->value . ':' . $this->severity->value);
    }
}
