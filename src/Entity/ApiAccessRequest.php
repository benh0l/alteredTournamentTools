<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ApiRequestStatus;
use App\Repository\ApiAccessRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ApiAccessRequestRepository::class)]
#[ORM\Table(name: 'api_access_requests')]
#[ORM\Index(name: 'idx_api_access_requests_status', columns: ['status'])]
#[ORM\Index(name: 'idx_api_access_requests_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_api_access_requests_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class ApiAccessRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'api.project_name.not_blank')]
    #[Assert\Length(min: 3, max: 100, minMessage: 'api.project_name.min_length', maxMessage: 'api.project_name.max_length')]
    private string $projectName;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'api.intended_use.not_blank')]
    #[Assert\Length(min: 20, max: 2000, minMessage: 'api.intended_use.min_length', maxMessage: 'api.intended_use.max_length')]
    private string $intendedUse;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Url(message: 'api.website_url.invalid')]
    private ?string $websiteUrl = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'api.discord_username.max_length')]
    private ?string $discordUsername = null;

    #[ORM\Column(type: 'string', length: 20, enumType: ApiRequestStatus::class)]
    private ApiRequestStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'reviewed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewed_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = ApiRequestStatus::PENDING;
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

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function setProjectName(string $projectName): self
    {
        $this->projectName = $projectName;

        return $this;
    }

    public function getIntendedUse(): string
    {
        return $this->intendedUse;
    }

    public function setIntendedUse(string $intendedUse): self
    {
        $this->intendedUse = $intendedUse;

        return $this;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    public function setWebsiteUrl(?string $websiteUrl): self
    {
        $this->websiteUrl = $websiteUrl;

        return $this;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function setDiscordUsername(?string $discordUsername): self
    {
        $this->discordUsername = $discordUsername;

        return $this;
    }

    public function getStatus(): ApiRequestStatus
    {
        return $this->status;
    }

    public function setStatus(ApiRequestStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): self
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function isPending(): bool
    {
        return $this->status === ApiRequestStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === ApiRequestStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === ApiRequestStatus::REJECTED;
    }

    public function approve(User $admin): self
    {
        $this->status = ApiRequestStatus::APPROVED;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->reviewedBy = $admin;

        return $this;
    }

    public function reject(User $admin, string $reason): self
    {
        $this->status = ApiRequestStatus::REJECTED;
        $this->rejectionReason = $reason;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->reviewedBy = $admin;

        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
