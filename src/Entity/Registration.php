<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Faction;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
#[ORM\Table(name: 'registrations')]
#[ORM\UniqueConstraint(name: 'uniq_registration_tournament_player', columns: ['tournament_id', 'player_id'])]
#[ORM\Index(name: 'idx_registrations_tournament', columns: ['tournament_id'])]
#[ORM\UniqueConstraint(name: 'uniq_registration_tournament_partner', columns: ['tournament_id', 'partner_id'])]
#[ORM\Index(name: 'idx_registrations_player', columns: ['player_id'])]
#[ORM\Index(name: 'idx_registrations_partner', columns: ['partner_id'])]
#[ORM\Index(name: 'idx_registrations_registered_at', columns: ['registered_at'])]
#[ORM\Index(name: 'idx_registrations_dropped_at', columns: ['dropped_at'])]
#[ORM\Index(name: 'idx_registrations_checked_in_at', columns: ['checked_in_at'])]
#[UniqueEntity(fields: ['tournament', 'player'], message: 'Ce joueur est deja inscrit a ce tournoi')]
class Registration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'registrations')]
    #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'registrations')]
    #[ORM\JoinColumn(name: 'player_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $player;

    /**
     * Teammate in team tournaments (Tournament::isTeamTournament()). Null in 1v1.
     * SET NULL on delete: the team stays registered if the partner deletes their account.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'partner_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $partner = null;

    #[ORM\Column(name: 'partner_decklist_url', type: 'string', length: 255, nullable: true)]
    #[Assert\Url(message: 'Le lien de la decklist doit etre une URL valide')]
    private ?string $partnerDecklistUrl = null;

    #[ORM\Column(name: 'partner_faction', type: 'string', length: 50, nullable: true, enumType: Faction::class)]
    private ?Faction $partnerFaction = null;

    #[ORM\Column(name: 'partner_faction2', type: 'string', length: 50, nullable: true, enumType: Faction::class)]
    private ?Faction $partnerFaction2 = null;

    #[ORM\Column(name: 'partner_hero', type: 'string', length: 100, nullable: true)]
    private ?string $partnerHero = null;

    #[ORM\Column(name: 'decklist_url', type: 'string', length: 255, nullable: true)]
    #[Assert\Url(message: 'Le lien de la decklist doit etre une URL valide')]
    private ?string $decklistUrl = null;

    #[ORM\Column(name: 'faction', type: 'string', length: 50, nullable: true, enumType: Faction::class)]
    private ?Faction $faction = null;

    #[ORM\Column(name: 'faction2', type: 'string', length: 50, nullable: true, enumType: Faction::class)]
    private ?Faction $faction2 = null;

    #[ORM\Column(name: 'hero', type: 'string', length: 100, nullable: true)]
    private ?string $hero = null;

    #[ORM\Column(name: 'registered_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column(name: 'unregister_token', type: 'string', length: 64, unique: true)]
    private string $unregisterToken;

    #[ORM\Column(name: 'dropped_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $droppedAt = null;

    #[ORM\Column(name: 'checked_in_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $checkedInAt = null;

    public function __construct(Tournament $tournament, User $player)
    {
        $this->tournament = $tournament;
        $this->player = $player;
        $this->registeredAt = new \DateTimeImmutable();
        $this->unregisterToken = Uuid::v4()->toRfc4122();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getPlayer(): User
    {
        return $this->player;
    }

    /**
     * All users competing under this registration.
     *
     * A registration is the competitor unit used by pairings, standings and brackets.
     * Always use this (or hasMember()) instead of getPlayer() when the question is
     * "which users are behind this competitor?".
     *
     * @return User[]
     */
    public function getMembers(): array
    {
        return $this->partner !== null ? [$this->player, $this->partner] : [$this->player];
    }

    /**
     * Check whether the given user competes under this registration.
     */
    public function hasMember(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach ($this->getMembers() as $member) {
            if ($member === $user || ($member->getId() !== null && $member->getId() === $user->getId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Name of the competitor as shown in pairings, standings and brackets.
     */
    public function getDisplayName(): string
    {
        return implode(' & ', array_map(
            static fn (User $member): string => $member->getDisplayName(),
            $this->getMembers(),
        ));
    }

    /**
     * Pseudo(s) of the competitor: the player's pseudo, or "Alice & Bob" for a team.
     */
    public function getPseudo(): string
    {
        return implode(' & ', array_map(
            static fn (User $member): string => $member->getPseudo(),
            $this->getMembers(),
        ));
    }

    public function getPartner(): ?User
    {
        return $this->partner;
    }

    public function setPartner(?User $partner): self
    {
        if ($partner !== null && ($partner === $this->player || ($partner->getId() !== null && $partner->getId() === $this->player->getId()))) {
            throw new \InvalidArgumentException('A player cannot be their own partner.');
        }

        $this->partner = $partner;

        return $this;
    }

    public function getPartnerDecklistUrl(): ?string
    {
        return $this->partnerDecklistUrl;
    }

    public function setPartnerDecklistUrl(?string $partnerDecklistUrl): self
    {
        $this->partnerDecklistUrl = $partnerDecklistUrl;

        return $this;
    }

    public function getPartnerFaction(): ?Faction
    {
        return $this->partnerFaction;
    }

    public function setPartnerFaction(?Faction $partnerFaction): self
    {
        $this->partnerFaction = $partnerFaction;

        return $this;
    }

    public function getPartnerFaction2(): ?Faction
    {
        return $this->partnerFaction2;
    }

    public function setPartnerFaction2(?Faction $partnerFaction2): self
    {
        $this->partnerFaction2 = $partnerFaction2;

        return $this;
    }

    public function getPartnerHero(): ?string
    {
        return $this->partnerHero;
    }

    public function setPartnerHero(?string $partnerHero): self
    {
        $this->partnerHero = $partnerHero;

        return $this;
    }

    public function getPartnerHeroLabel(): ?string
    {
        return $this->partnerHero !== null ? Faction::getHeroLabel($this->partnerHero) : null;
    }

    public function getPartnerHeroImage(): ?string
    {
        return $this->partnerHero !== null ? Faction::getHeroImage($this->partnerHero) : null;
    }

    public function getDecklistUrl(): ?string
    {
        return $this->decklistUrl;
    }

    public function setDecklistUrl(?string $decklistUrl): self
    {
        $this->decklistUrl = $decklistUrl;

        return $this;
    }

    public function getFaction(): ?Faction
    {
        return $this->faction;
    }

    public function setFaction(?Faction $faction): self
    {
        $this->faction = $faction;

        return $this;
    }

    public function getFaction2(): ?Faction
    {
        return $this->faction2;
    }

    public function setFaction2(?Faction $faction2): self
    {
        $this->faction2 = $faction2;

        return $this;
    }

    public function getHero(): ?string
    {
        return $this->hero;
    }

    public function setHero(?string $hero): self
    {
        $this->hero = $hero;

        return $this;
    }

    public function getHeroLabel(): ?string
    {
        if ($this->hero === null) {
            return null;
        }

        return Faction::getHeroLabel($this->hero);
    }

    public function getHeroImage(): ?string
    {
        if ($this->hero === null) {
            return null;
        }

        return Faction::getHeroImage($this->hero);
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function getUnregisterToken(): string
    {
        return $this->unregisterToken;
    }

    public function getDroppedAt(): ?\DateTimeImmutable
    {
        return $this->droppedAt;
    }

    /**
     * Check if the player has dropped from the tournament.
     */
    public function isDropped(): bool
    {
        return $this->droppedAt !== null;
    }

    /**
     * Mark the player as dropped from the tournament.
     * Sets droppedAt to current timestamp if not already dropped.
     */
    public function drop(): self
    {
        if ($this->droppedAt === null) {
            $this->droppedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    /**
     * Reverse a drop action (organizer only).
     * Clears the droppedAt timestamp.
     */
    public function undrop(): self
    {
        $this->droppedAt = null;

        return $this;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    /**
     * Check if the player has checked in.
     */
    public function isCheckedIn(): bool
    {
        return $this->checkedInAt !== null;
    }

    /**
     * Mark the player as checked in.
     * Sets checkedInAt to current timestamp if not already checked in.
     */
    public function checkIn(): self
    {
        if ($this->checkedInAt === null) {
            $this->checkedInAt = new \DateTimeImmutable();
        }

        return $this;
    }

    /**
     * Reverse a check-in action (organizer only).
     * Clears the checkedInAt timestamp.
     */
    public function undoCheckIn(): self
    {
        $this->checkedInAt = null;

        return $this;
    }
}
