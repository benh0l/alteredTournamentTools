<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DecklistTransparency;
use App\Enum\GroupFormationMethod;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Repository\TournamentRepository;
use App\Enum\RoundStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TournamentRepository::class)]
#[ORM\Table(name: 'tournaments')]
#[ORM\Index(name: 'idx_tournaments_status', columns: ['status'])]
#[ORM\Index(name: 'idx_tournaments_organizer', columns: ['organizer_id'])]
#[ORM\Index(name: 'idx_tournaments_visibility', columns: ['visibility'])]
#[ORM\Index(name: 'idx_tournaments_date', columns: ['date'])]
#[ORM\HasLifecycleCallbacks]
class Tournament
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le nom du tournoi est requis')]
    #[Assert\Length(min: 3, max: 100, minMessage: 'Le nom doit faire au moins 3 caracteres', maxMessage: 'Le nom ne peut pas depasser 100 caracteres')]
    private string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'organizer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $organizer = null;

    #[ORM\Column(name: 'date', type: 'date_immutable')]
    #[Assert\NotNull(message: 'La date du tournoi est requise')]
    private \DateTimeImmutable $date;

    #[ORM\Column(name: 'time', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $time = null;

    #[ORM\Column(name: 'location', type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    /**
     * Latitude for geolocation search (FR59).
     */
    #[ORM\Column(name: 'latitude', type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    /**
     * Longitude for geolocation search (FR59).
     */
    #[ORM\Column(name: 'longitude', type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'entry_fee', type: 'string', length: 50, nullable: true)]
    private ?string $entryFee = null;

    #[ORM\Column(name: 'prizes', type: 'text', nullable: true)]
    private ?string $prizes = null;

    #[ORM\Column(name: 'altered_gg_link', type: 'string', length: 255, nullable: true)]
    #[Assert\Url(message: 'Le lien Altered.gg doit etre une URL valide')]
    private ?string $alteredGgLink = null;

    #[ORM\Column(name: 'format', type: 'string', length: 30, enumType: TournamentFormat::class)]
    private TournamentFormat $format;

    #[ORM\Column(name: 'structure', type: 'string', length: 30, enumType: TournamentStructure::class)]
    private TournamentStructure $structure;

    #[ORM\Column(name: 'visibility', type: 'string', length: 10, enumType: TournamentVisibility::class)]
    private TournamentVisibility $visibility;

    #[ORM\Column(name: 'decklist_transparency', type: 'string', length: 10, enumType: DecklistTransparency::class)]
    private DecklistTransparency $decklistTransparency;

    #[ORM\Column(name: 'status', type: 'string', length: 15, enumType: TournamentStatus::class)]
    private TournamentStatus $status;

    #[ORM\Column(name: 'swiss_match_format', type: 'string', length: 5, enumType: MatchFormat::class)]
    private MatchFormat $swissMatchFormat;

    #[ORM\Column(name: 'elimination_match_format', type: 'string', length: 5, nullable: true, enumType: MatchFormat::class)]
    private ?MatchFormat $eliminationMatchFormat = null;

    #[ORM\Column(name: 'swiss_rounds', type: 'smallint', nullable: true)]
    #[Assert\Positive(message: 'Le nombre de rondes doit etre positif')]
    private ?int $swissRounds = null;

    #[ORM\Column(name: 'expected_players', type: 'smallint', nullable: true)]
    #[Assert\Positive(message: 'Le nombre de joueurs attendus doit etre positif')]
    private ?int $expectedPlayers = null;

    #[ORM\Column(name: 'max_players', type: 'smallint', nullable: true)]
    #[Assert\Positive(message: 'Le nombre maximum de joueurs doit etre positif')]
    private ?int $maxPlayers = null;

    #[ORM\Column(name: 'top_cut_size', type: 'smallint', nullable: true)]
    #[Assert\Choice(choices: [4, 8, 16, 32], message: 'La taille du Top Cut doit etre 4, 8, 16 ou 32')]
    private ?int $topCutSize = null;

    /**
     * Number of groups for GROUP_STAGE_ELIMINATION structure.
     */
    #[ORM\Column(name: 'group_count', type: 'smallint', nullable: true)]
    #[Assert\Range(min: 2, max: 16, notInRangeMessage: 'Le nombre de poules doit etre entre {{ min }} et {{ max }}')]
    private ?int $groupCount = null;

    /**
     * Number of players per group for GROUP_STAGE_ELIMINATION structure.
     */
    #[ORM\Column(name: 'players_per_group', type: 'smallint', nullable: true)]
    #[Assert\Range(min: 3, max: 16, notInRangeMessage: 'Le nombre de joueurs par poule doit etre entre {{ min }} et {{ max }}')]
    private ?int $playersPerGroup = null;

    /**
     * Number of qualifiers per group for GROUP_STAGE_ELIMINATION structure.
     */
    #[ORM\Column(name: 'qualifiers_per_group', type: 'smallint', nullable: true)]
    #[Assert\Range(min: 1, max: 8, notInRangeMessage: 'Le nombre de qualifies par poule doit etre entre {{ min }} et {{ max }}')]
    private ?int $qualifiersPerGroup = null;

    /**
     * Method used to form groups (RANDOM or SERPENTINE).
     */
    #[ORM\Column(name: 'group_formation_method', type: 'string', length: 20, nullable: true, enumType: GroupFormationMethod::class)]
    private ?GroupFormationMethod $groupFormationMethod = null;

    #[ORM\Column(name: 'registrations_closed', type: 'boolean')]
    private bool $registrationsClosed = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * Whether results are published (FR56, FR57).
     * For PUBLIC tournaments: auto-set to true when completed.
     * For PRIVATE tournaments: organizer can choose to publish.
     */
    #[ORM\Column(name: 'results_published', type: 'boolean')]
    private bool $resultsPublished = false;

    /** @var Collection<int, Registration> */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'tournament', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $registrations;

    /** @var Collection<int, Round> */
    #[ORM\OneToMany(targetEntity: Round::class, mappedBy: 'tournament', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['roundNumber' => 'ASC'])]
    private Collection $rounds;

    /** @var Collection<int, TournamentGroup> */
    #[ORM\OneToMany(targetEntity: TournamentGroup::class, mappedBy: 'tournament', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['letter' => 'ASC'])]
    private Collection $groups;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = TournamentStatus::DRAFT;
        $this->registrations = new ArrayCollection();
        $this->rounds = new ArrayCollection();
        $this->groups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }

    public function setOrganizer(?User $organizer): self
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getTime(): ?\DateTimeImmutable
    {
        return $this->time;
    }

    public function setTime(?\DateTimeImmutable $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude !== null ? (float) $this->latitude : null;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude !== null ? (string) $latitude : null;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude !== null ? (float) $this->longitude : null;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude !== null ? (string) $longitude : null;

        return $this;
    }

    /**
     * Set coordinates from geocoding (FR59).
     */
    public function setCoordinates(?float $latitude, ?float $longitude): self
    {
        $this->setLatitude($latitude);
        $this->setLongitude($longitude);

        return $this;
    }

    /**
     * Check if tournament has coordinates for map display.
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getEntryFee(): ?string
    {
        return $this->entryFee;
    }

    public function setEntryFee(?string $entryFee): self
    {
        $this->entryFee = $entryFee;

        return $this;
    }

    public function getPrizes(): ?string
    {
        return $this->prizes;
    }

    public function setPrizes(?string $prizes): self
    {
        $this->prizes = $prizes;

        return $this;
    }

    public function getAlteredGgLink(): ?string
    {
        return $this->alteredGgLink;
    }

    public function setAlteredGgLink(?string $alteredGgLink): self
    {
        $this->alteredGgLink = $alteredGgLink;

        return $this;
    }

    public function getFormat(): TournamentFormat
    {
        return $this->format;
    }

    public function setFormat(TournamentFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getStructure(): TournamentStructure
    {
        return $this->structure;
    }

    public function setStructure(TournamentStructure $structure): self
    {
        $this->structure = $structure;

        return $this;
    }

    public function getVisibility(): TournamentVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(TournamentVisibility $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getDecklistTransparency(): DecklistTransparency
    {
        return $this->decklistTransparency;
    }

    public function setDecklistTransparency(DecklistTransparency $decklistTransparency): self
    {
        $this->decklistTransparency = $decklistTransparency;

        return $this;
    }

    public function getStatus(): TournamentStatus
    {
        return $this->status;
    }

    public function setStatus(TournamentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSwissMatchFormat(): MatchFormat
    {
        return $this->swissMatchFormat;
    }

    public function setSwissMatchFormat(MatchFormat $swissMatchFormat): self
    {
        $this->swissMatchFormat = $swissMatchFormat;

        return $this;
    }

    public function getEliminationMatchFormat(): ?MatchFormat
    {
        return $this->eliminationMatchFormat;
    }

    public function setEliminationMatchFormat(?MatchFormat $eliminationMatchFormat): self
    {
        $this->eliminationMatchFormat = $eliminationMatchFormat;

        return $this;
    }

    public function getSwissRounds(): ?int
    {
        return $this->swissRounds;
    }

    public function setSwissRounds(?int $swissRounds): self
    {
        $this->swissRounds = $swissRounds;

        return $this;
    }

    public function getExpectedPlayers(): ?int
    {
        return $this->expectedPlayers;
    }

    public function setExpectedPlayers(?int $expectedPlayers): self
    {
        $this->expectedPlayers = $expectedPlayers;

        return $this;
    }

    public function getMaxPlayers(): ?int
    {
        return $this->maxPlayers;
    }

    public function setMaxPlayers(?int $maxPlayers): self
    {
        $this->maxPlayers = $maxPlayers;

        return $this;
    }

    /**
     * Check if there are available spots for registration.
     */
    public function hasAvailableSpots(): bool
    {
        if ($this->maxPlayers === null) {
            return true; // No limit
        }

        return $this->registrations->count() < $this->maxPlayers;
    }

    /**
     * Get the number of available spots.
     */
    public function getAvailableSpotsCount(): ?int
    {
        if ($this->maxPlayers === null) {
            return null;
        }

        return max(0, $this->maxPlayers - $this->registrations->count());
    }

    public function getTopCutSize(): ?int
    {
        return $this->topCutSize;
    }

    public function setTopCutSize(?int $topCutSize): self
    {
        $this->topCutSize = $topCutSize;

        return $this;
    }

    public function getGroupCount(): ?int
    {
        return $this->groupCount;
    }

    public function setGroupCount(?int $groupCount): self
    {
        $this->groupCount = $groupCount;

        return $this;
    }

    public function getPlayersPerGroup(): ?int
    {
        return $this->playersPerGroup;
    }

    public function setPlayersPerGroup(?int $playersPerGroup): self
    {
        $this->playersPerGroup = $playersPerGroup;

        return $this;
    }

    public function getQualifiersPerGroup(): ?int
    {
        return $this->qualifiersPerGroup;
    }

    public function setQualifiersPerGroup(?int $qualifiersPerGroup): self
    {
        $this->qualifiersPerGroup = $qualifiersPerGroup;

        return $this;
    }

    public function getGroupFormationMethod(): ?GroupFormationMethod
    {
        return $this->groupFormationMethod;
    }

    public function setGroupFormationMethod(?GroupFormationMethod $groupFormationMethod): self
    {
        $this->groupFormationMethod = $groupFormationMethod;

        return $this;
    }

    /**
     * Get total number of qualifiers from all groups.
     */
    public function getTotalQualifiers(): ?int
    {
        if ($this->groupCount === null || $this->qualifiersPerGroup === null) {
            return null;
        }

        return $this->groupCount * $this->qualifiersPerGroup;
    }

    /**
     * Get total expected players for group stage.
     */
    public function getTotalGroupPlayers(): ?int
    {
        if ($this->groupCount === null || $this->playersPerGroup === null) {
            return null;
        }

        return $this->groupCount * $this->playersPerGroup;
    }

    public function isRegistrationsClosed(): bool
    {
        return $this->registrationsClosed;
    }

    public function setRegistrationsClosed(bool $registrationsClosed): self
    {
        $this->registrationsClosed = $registrationsClosed;

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

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    /**
     * Check if results are published (FR56, FR57).
     */
    public function isResultsPublished(): bool
    {
        return $this->resultsPublished;
    }

    /**
     * Set results publication status.
     */
    public function setResultsPublished(bool $resultsPublished): self
    {
        $this->resultsPublished = $resultsPublished;

        return $this;
    }

    /**
     * Publish results (FR56, FR57).
     * For PUBLIC tournaments: called automatically when tournament completes.
     * For PRIVATE tournaments: called when organizer chooses to publish.
     */
    public function publishResults(): self
    {
        $this->resultsPublished = true;

        return $this;
    }

    /**
     * Check if tournament can be edited.
     * Editing is allowed in DRAFT and PUBLISHED status until the first round is generated.
     */
    public function isEditable(): bool
    {
        // Can edit if DRAFT
        if ($this->status === TournamentStatus::DRAFT) {
            return true;
        }

        // Can edit if PUBLISHED but no rounds started yet
        if ($this->status === TournamentStatus::PUBLISHED && !$this->hasRounds()) {
            return true;
        }

        return false;
    }

    /**
     * Check if tournament is in DRAFT status.
     */
    public function isDraft(): bool
    {
        return $this->status === TournamentStatus::DRAFT;
    }

    /**
     * Check if tournament is PUBLISHED.
     */
    public function isPublished(): bool
    {
        return $this->status === TournamentStatus::PUBLISHED;
    }

    /**
     * Check if tournament is ONGOING.
     */
    public function isOngoing(): bool
    {
        return $this->status === TournamentStatus::ONGOING;
    }

    /**
     * Check if tournament is COMPLETED.
     */
    public function isCompleted(): bool
    {
        return $this->status === TournamentStatus::COMPLETED;
    }

    /**
     * Check if tournament is CANCELLED.
     */
    public function isCancelled(): bool
    {
        return $this->status === TournamentStatus::CANCELLED;
    }

    /**
     * Check if tournament accepts registrations.
     */
    public function acceptsRegistrations(): bool
    {
        return $this->status->acceptsRegistrations() && !$this->registrationsClosed;
    }

    /**
     * Check if tournament is active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if tournament has ended.
     */
    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /**
     * Check if tournament has elimination phase.
     */
    public function hasElimination(): bool
    {
        return $this->structure->hasElimination();
    }

    /**
     * Check if tournament has Swiss phase.
     */
    public function hasSwiss(): bool
    {
        return $this->structure->hasSwiss();
    }

    /**
     * Check if tournament has group stage.
     */
    public function hasGroupStage(): bool
    {
        return $this->structure->hasGroupStage();
    }

    /**
     * Check if tournament is currently in group phase.
     * True if groups exist and we haven't started elimination rounds yet.
     */
    public function isInGroupPhase(): bool
    {
        if (!$this->hasGroupStage()) {
            return false;
        }

        // If no groups formed yet, we're not in group phase yet
        if ($this->groups->isEmpty()) {
            return false;
        }

        // Check if any elimination round has started
        foreach ($this->rounds as $round) {
            if ($round->isEliminationRound()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if tournament is currently in elimination phase.
     * True if we have started elimination rounds.
     */
    public function isInEliminationPhase(): bool
    {
        if (!$this->hasElimination()) {
            return false;
        }

        foreach ($this->rounds as $round) {
            if ($round->isEliminationRound()) {
                return true;
            }
        }

        return false;
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
     * Get the count of registered players.
     */
    public function getRegistrationCount(): int
    {
        return $this->registrations->count();
    }

    /**
     * Check if a user is registered in this tournament.
     */
    public function isPlayerRegistered(User $player): bool
    {
        return $this->registrations->exists(
            fn (int $key, Registration $registration): bool => $registration->getPlayer() === $player
        );
    }

    /**
     * Check if the organizer is also registered as a player.
     *
     * NOTE: Organizers can participate in their own tournaments (FR26).
     * When this is true, special care must be taken:
     * - Pairings are generated algorithmically and remain neutral
     * - If a dispute involves the organizer, an admin must resolve it
     */
    public function isOrganizerPlaying(): bool
    {
        return $this->isPlayerRegistered($this->organizer);
    }

    /**
     * @return Collection<int, Round>
     */
    public function getRounds(): Collection
    {
        return $this->rounds;
    }

    public function addRound(Round $round): self
    {
        if (!$this->rounds->contains($round)) {
            $this->rounds->add($round);
            $round->setTournament($this);
        }

        return $this;
    }

    public function removeRound(Round $round): self
    {
        $this->rounds->removeElement($round);

        return $this;
    }

    /**
     * Get the current (ongoing) round.
     */
    public function getCurrentRound(): ?Round
    {
        foreach ($this->rounds as $round) {
            if ($round->getStatus() === RoundStatus::ONGOING) {
                return $round;
            }
        }

        return null;
    }

    /**
     * Get the latest round (highest round number).
     */
    public function getLatestRound(): ?Round
    {
        if ($this->rounds->isEmpty()) {
            return null;
        }

        $rounds = $this->rounds->toArray();

        return $rounds[count($rounds) - 1];
    }

    /**
     * Get the count of completed rounds.
     */
    public function getCompletedRoundsCount(): int
    {
        return $this->rounds->filter(
            fn (Round $round): bool => $round->getStatus() === RoundStatus::COMPLETED
        )->count();
    }

    /**
     * Get the total count of rounds.
     */
    public function getRoundsCount(): int
    {
        return $this->rounds->count();
    }

    /**
     * Check if the tournament has any rounds.
     */
    public function hasRounds(): bool
    {
        return !$this->rounds->isEmpty();
    }

    /**
     * Check if the tournament has reached its configured Swiss rounds limit.
     */
    public function hasReachedSwissRoundLimit(): bool
    {
        // If no limit is configured, we can always continue
        if ($this->swissRounds === null) {
            return false;
        }

        return $this->getRoundsCount() >= $this->swissRounds;
    }

    /**
     * Check if another round can be started.
     * Returns true if the tournament is ongoing and hasn't reached the round limit.
     */
    public function canStartNextRound(): bool
    {
        if (!$this->isOngoing()) {
            return false;
        }

        return !$this->hasReachedSwissRoundLimit();
    }

    /**
     * @return Collection<int, TournamentGroup>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(TournamentGroup $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
            $group->setTournament($this);
        }

        return $this;
    }

    public function removeGroup(TournamentGroup $group): self
    {
        $this->groups->removeElement($group);

        return $this;
    }

    /**
     * Check if the tournament has any groups.
     */
    public function hasGroups(): bool
    {
        return !$this->groups->isEmpty();
    }

    /**
     * Get the count of groups.
     */
    public function getGroupsCount(): int
    {
        return $this->groups->count();
    }

    /**
     * Find a group by its letter.
     */
    public function getGroupByLetter(string $letter): ?TournamentGroup
    {
        $letter = strtoupper($letter);

        foreach ($this->groups as $group) {
            if ($group->getLetter() === $letter) {
                return $group;
            }
        }

        return null;
    }
}
