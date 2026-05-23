<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContactStatus;
use App\Enum\ContactSubject;
use App\Repository\ContactReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactReportRepository::class)]
#[ORM\Table(name: 'contact_reports')]
#[ORM\Index(name: 'idx_contact_reports_status', columns: ['status'])]
#[ORM\Index(name: 'idx_contact_reports_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class ContactReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 20, enumType: ContactSubject::class)]
    private ContactSubject $subject;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'contact.message.not_blank')]
    #[Assert\Length(min: 10, max: 2000, minMessage: 'contact.message.min_length', maxMessage: 'contact.message.max_length')]
    private string $message;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $pageUrl = null;

    #[ORM\Column(type: 'string', length: 20, enumType: ContactStatus::class)]
    private ContactStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = ContactStatus::NEW;
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

    public function getSubject(): ContactSubject
    {
        return $this->subject;
    }

    public function setSubject(ContactSubject $subject): self
    {
        $this->subject = $subject;

        return $this;
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

    public function getPageUrl(): ?string
    {
        return $this->pageUrl;
    }

    public function setPageUrl(?string $pageUrl): self
    {
        $this->pageUrl = $pageUrl;

        return $this;
    }

    public function getStatus(): ContactStatus
    {
        return $this->status;
    }

    public function setStatus(ContactStatus $status): self
    {
        $this->status = $status;

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

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
