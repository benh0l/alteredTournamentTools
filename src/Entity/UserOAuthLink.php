<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserOAuthLinkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserOAuthLinkRepository::class)]
#[ORM\Table(name: 'user_oauth_link')]
#[ORM\UniqueConstraint(name: 'unique_provider_user', columns: ['provider', 'provider_user_id'])]
#[ORM\UniqueConstraint(name: 'unique_user_provider', columns: ['user_id', 'provider'])]
#[ORM\Index(columns: ['provider_user_id'], name: 'idx_provider_user_id')]
class UserOAuthLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'oauthLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 50)]
    private string $provider = 'altered_reunion';

    #[ORM\Column(name: 'provider_user_id', length: 255)]
    private string $providerUserId;

    #[ORM\Column(name: 'provider_username', length: 255, nullable: true)]
    private ?string $providerUsername = null;

    #[ORM\Column(name: 'linked_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $linkedAt;

    public function __construct()
    {
        $this->linkedAt = new \DateTimeImmutable();
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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderUserId(): string
    {
        return $this->providerUserId;
    }

    public function setProviderUserId(string $providerUserId): self
    {
        $this->providerUserId = $providerUserId;

        return $this;
    }

    public function getProviderUsername(): ?string
    {
        return $this->providerUsername;
    }

    public function setProviderUsername(?string $providerUsername): self
    {
        $this->providerUsername = $providerUsername;

        return $this;
    }

    public function getLinkedAt(): \DateTimeImmutable
    {
        return $this->linkedAt;
    }
}
