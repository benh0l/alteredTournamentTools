<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RoundStatus;
use App\Repository\RoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoundRepository::class)]
#[ORM\Table(name: 'rounds')]
#[ORM\UniqueConstraint(name: 'uniq_rounds_tournament_number', columns: ['tournament_id', 'round_number'])]
#[ORM\Index(name: 'idx_rounds_tournament', columns: ['tournament_id'])]
#[ORM\Index(name: 'idx_rounds_status', columns: ['status'])]
class Round
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'rounds')]
    #[ORM\JoinColumn(name: 'tournament_id', nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\Column(name: 'round_number', type: 'integer')]
    private int $roundNumber;

    #[ORM\Column(name: 'status', type: 'string', length: 20, enumType: RoundStatus::class)]
    private RoundStatus $status = RoundStatus::PENDING;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Reference to group for GROUP_STAGE_ELIMINATION tournaments.
     * Null for Swiss rounds or elimination rounds.
     */
    #[ORM\ManyToOne(targetEntity: TournamentGroup::class)]
    #[ORM\JoinColumn(name: 'group_id', nullable: true, onDelete: 'CASCADE')]
    private ?TournamentGroup $group = null;

    /**
     * Whether this round is part of elimination phase.
     * False for Swiss rounds and group stage rounds.
     */
    #[ORM\Column(name: 'is_elimination_round', type: 'boolean')]
    private bool $isEliminationRound = false;

    /** @var Collection<int, TournamentMatch> */
    #[ORM\OneToMany(targetEntity: TournamentMatch::class, mappedBy: 'round', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['tableNumber' => 'ASC'])]
    private Collection $matches;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->matches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRoundNumber(): int
    {
        return $this->roundNumber;
    }

    public function setRoundNumber(int $roundNumber): self
    {
        $this->roundNumber = $roundNumber;

        return $this;
    }

    public function getStatus(): RoundStatus
    {
        return $this->status;
    }

    public function setStatus(RoundStatus $status): self
    {
        $this->status = $status;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getGroup(): ?TournamentGroup
    {
        return $this->group;
    }

    public function setGroup(?TournamentGroup $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Check if this round belongs to a specific group (for group stage).
     */
    public function hasGroup(): bool
    {
        return $this->group !== null;
    }

    public function isEliminationRound(): bool
    {
        return $this->isEliminationRound;
    }

    public function setIsEliminationRound(bool $isEliminationRound): self
    {
        $this->isEliminationRound = $isEliminationRound;

        return $this;
    }

    /**
     * Check if this is a group stage round.
     */
    public function isGroupStageRound(): bool
    {
        return $this->group !== null && !$this->isEliminationRound;
    }

    /**
     * @return Collection<int, TournamentMatch>
     */
    public function getMatches(): Collection
    {
        return $this->matches;
    }

    public function addMatch(TournamentMatch $match): self
    {
        if (!$this->matches->contains($match)) {
            $this->matches->add($match);
            $match->setRound($this);
        }

        return $this;
    }

    public function removeMatch(TournamentMatch $match): self
    {
        $this->matches->removeElement($match);

        return $this;
    }

    /**
     * Check if round is pending (not started).
     */
    public function isPending(): bool
    {
        return $this->status === RoundStatus::PENDING;
    }

    /**
     * Check if round is ongoing.
     */
    public function isOngoing(): bool
    {
        return $this->status === RoundStatus::ONGOING;
    }

    /**
     * Check if round is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === RoundStatus::COMPLETED;
    }

    /**
     * Start the round.
     */
    public function start(): self
    {
        $this->status = RoundStatus::ONGOING;
        $this->startedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Complete the round.
     */
    public function complete(): self
    {
        $this->status = RoundStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Get count of completed matches.
     */
    public function getCompletedMatchesCount(): int
    {
        return $this->matches->filter(
            fn (TournamentMatch $match): bool => $match->isCompleted()
        )->count();
    }

    /**
     * Get count of pending matches.
     */
    public function getPendingMatchesCount(): int
    {
        return $this->matches->filter(
            fn (TournamentMatch $match): bool => $match->isPending() || $match->isOngoing()
        )->count();
    }

    /**
     * Check if all matches are completed.
     */
    public function areAllMatchesCompleted(): bool
    {
        if ($this->matches->isEmpty()) {
            return false;
        }

        return $this->matches->forAll(
            fn (int $key, TournamentMatch $match): bool => $match->isCompleted()
        );
    }

    /**
     * Check if there are any disputed matches.
     */
    public function hasDisputes(): bool
    {
        return $this->matches->exists(
            fn (int $key, TournamentMatch $match): bool => $match->isDisputed()
        );
    }
}
