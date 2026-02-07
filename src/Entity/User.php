<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'idx_users_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est deja utilisee')]
#[UniqueEntity(fields: ['pseudo'], message: 'Ce pseudo est deja utilise')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'email', type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(name: 'password', type: 'string')]
    private string $password;

    /** @var array<string> */
    #[ORM\Column(name: 'roles', type: 'json')]
    private array $roles = [];

    #[ORM\Column(name: 'pseudo', type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]+$/', message: 'Pseudo can only contain letters, numbers, underscores and dashes')]
    private string $pseudo;

    #[ORM\Column(name: 'name', type: 'string', length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'avatar', type: 'string', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(name: 'team_tag', type: 'string', length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]*$/', message: 'Le tag ne peut contenir que des lettres, chiffres, tirets et underscores')]
    private ?string $teamTag = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_verified', type: 'boolean')]
    private bool $isVerified = false;

    /**
     * Notification preferences (FR67).
     *
     * @var array{d_minus_1: bool, h_minus_1: bool, round_start: bool, channels: array{email: bool, push: bool}}
     */
    #[ORM\Column(name: 'notification_preferences', type: 'json')]
    private array $notificationPreferences = [
        'd_minus_1' => true,
        'h_minus_1' => true,
        'round_start' => true,
        'channels' => [
            'email' => true,
            'push' => false,
        ],
    ];

    /**
     * User's preferred theme color.
     * Valid values: orange, blue, green, purple, rose
     */
    #[ORM\Column(name: 'theme_color', type: 'string', length: 20, nullable: true)]
    private ?string $themeColor = 'orange';

    /**
     * User's preferred theme mode.
     * Valid values: light, dark
     */
    #[ORM\Column(name: 'theme_mode', type: 'string', length: 10, nullable: true)]
    private ?string $themeMode = 'light';

    /**
     * Whether this user is a guest (created by organizer, no real account).
     */
    #[ORM\Column(name: 'is_guest', type: 'boolean', options: ['default' => false])]
    private bool $isGuest = false;

    /** @var Collection<int, Registration> */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'player', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $registrations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->roles = ['ROLE_USER'];
        $this->registrations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return array_unique($roles);
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function addRole(string $role): self
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRole(string $role): self
    {
        if ($role === 'ROLE_USER') {
            return $this; // Cannot remove ROLE_USER
        }

        $this->roles = array_filter($this->roles, fn (string $r) => $r !== $role);

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getPseudo(): string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): self
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getTeamTag(): ?string
    {
        return $this->teamTag;
    }

    public function setTeamTag(?string $teamTag): self
    {
        $this->teamTag = $teamTag;

        return $this;
    }

    /**
     * Get display name with team tag if set.
     * Format: [TAG] Pseudo or just Pseudo if no tag.
     * For guest accounts, returns their real name instead.
     */
    public function getDisplayName(): string
    {
        // For guests, return their real name
        if ($this->isGuest && $this->name) {
            return $this->name;
        }

        if ($this->teamTag) {
            return '[' . $this->teamTag . '] ' . $this->pseudo;
        }

        return $this->pseudo;
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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isGuest(): bool
    {
        return $this->isGuest;
    }

    public function setIsGuest(bool $isGuest): self
    {
        $this->isGuest = $isGuest;

        return $this;
    }

    /**
     * @return Collection<int, Registration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function addRegistration(Registration $registration): self
    {
        if (!$this->registrations->contains($registration)) {
            $this->registrations->add($registration);
        }

        return $this;
    }

    public function removeRegistration(Registration $registration): self
    {
        $this->registrations->removeElement($registration);

        return $this;
    }

    /**
     * Get notification preferences (FR67).
     *
     * @return array{d_minus_1: bool, h_minus_1: bool, round_start: bool, channels: array{email: bool, push: bool}}
     */
    public function getNotificationPreferences(): array
    {
        return $this->notificationPreferences;
    }

    /**
     * Set notification preferences (FR67).
     *
     * @param array{d_minus_1?: bool, h_minus_1?: bool, round_start?: bool, channels?: array{email?: bool, push?: bool}} $notificationPreferences
     */
    public function setNotificationPreferences(array $notificationPreferences): self
    {
        $this->notificationPreferences = array_merge($this->notificationPreferences, $notificationPreferences);

        return $this;
    }

    /**
     * Check if a specific notification type is enabled.
     */
    public function isNotificationEnabled(string $type): bool
    {
        return $this->notificationPreferences[$type] ?? true;
    }

    /**
     * Check if email channel is enabled.
     */
    public function isEmailNotificationEnabled(): bool
    {
        return $this->notificationPreferences['channels']['email'] ?? true;
    }

    /**
     * Check if push channel is enabled.
     */
    public function isPushNotificationEnabled(): bool
    {
        return $this->notificationPreferences['channels']['push'] ?? false;
    }

    /**
     * Set a specific notification preference.
     */
    public function setNotificationPreference(string $key, bool $value): self
    {
        if (str_starts_with($key, 'channels.')) {
            $channel = substr($key, strlen('channels.'));
            $this->notificationPreferences['channels'][$channel] = $value;
        } else {
            $this->notificationPreferences[$key] = $value;
        }

        return $this;
    }

    /**
     * Get user's preferred theme color.
     */
    public function getThemeColor(): string
    {
        return $this->themeColor ?? 'orange';
    }

    /**
     * Set user's preferred theme color.
     */
    public function setThemeColor(?string $themeColor): self
    {
        $validColors = ['orange', 'blue', 'green', 'purple', 'rose'];
        if ($themeColor === null || in_array($themeColor, $validColors, true)) {
            $this->themeColor = $themeColor;
        }

        return $this;
    }

    /**
     * Get user's preferred theme mode.
     */
    public function getThemeMode(): string
    {
        return $this->themeMode ?? 'light';
    }

    /**
     * Set user's preferred theme mode.
     */
    public function setThemeMode(?string $themeMode): self
    {
        $validModes = ['light', 'dark'];
        if ($themeMode === null || in_array($themeMode, $validModes, true)) {
            $this->themeMode = $themeMode;
        }

        return $this;
    }
}
