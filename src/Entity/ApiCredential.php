<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApiCredentialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiCredentialRepository::class)]
#[ORM\Table(name: 'api_credentials')]
#[ORM\Index(name: 'idx_api_credentials_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_api_credentials_active', columns: ['is_active'])]
#[ORM\UniqueConstraint(name: 'uniq_api_credentials_api_key', columns: ['api_key'])]
class ApiCredential
{
    private const KEY_PREFIX = 'att_';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\OneToOne(targetEntity: ApiAccessRequest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ApiAccessRequest $request;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $apiKey;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->apiKey = self::generateApiKey();
    }

    public static function generateApiKey(): string
    {
        // Generate a 64-character API key: att_ (4 chars) + 60 hex chars
        return self::KEY_PREFIX . bin2hex(random_bytes(30));
    }

    public static function getKeyPrefix(): string
    {
        return self::KEY_PREFIX;
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

    public function getRequest(): ApiAccessRequest
    {
        return $this->request;
    }

    public function setRequest(ApiAccessRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getMaskedApiKey(): string
    {
        // Show only the prefix and last 4 characters: att_****...****abcd
        return substr($this->apiKey, 0, 8) . str_repeat('*', 48) . substr($this->apiKey, -4);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function updateLastUsedAt(): self
    {
        $this->lastUsedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(): self
    {
        $this->isActive = false;
        $this->revokedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
