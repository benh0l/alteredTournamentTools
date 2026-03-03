<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MatchFormat;
use App\Enum\MatchStatus;
use App\Repository\TournamentMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentMatchRepository::class)]
#[ORM\Table(name: 'matches')]
#[ORM\Index(name: 'idx_matches_round', columns: ['round_id'])]
#[ORM\Index(name: 'idx_matches_status', columns: ['status'])]
#[ORM\Index(name: 'idx_matches_player1', columns: ['player1_id'])]
#[ORM\Index(name: 'idx_matches_player2', columns: ['player2_id'])]
class TournamentMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'matches')]
    #[ORM\JoinColumn(name: 'round_id', nullable: false, onDelete: 'CASCADE')]
    private Round $round;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(name: 'player1_id', nullable: false)]
    private Registration $player1;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(name: 'player2_id', nullable: true)]
    private ?Registration $player2 = null;

    #[ORM\Column(name: 'table_number', type: 'integer')]
    private int $tableNumber;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $result = null;

    #[ORM\Column(name: 'status', type: 'string', length: 20, enumType: MatchStatus::class)]
    private MatchStatus $status = MatchStatus::PENDING;

    #[ORM\Column(name: 'is_bye', type: 'boolean')]
    private bool $isBye = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * History of dispute events and resolutions.
     * Format: [{"timestamp": "...", "type": "created|resolved", "data": {...}}]
     *
     * @var array<int, array{timestamp: string, type: string, data: array<string, mixed>}>
     */
    #[ORM\Column(name: 'dispute_history', type: 'json', nullable: true)]
    private ?array $disputeHistory = null;

    /**
     * Reference to group for GROUP_STAGE_ELIMINATION tournaments.
     * Allows easy filtering of matches by group.
     */
    #[ORM\ManyToOne(targetEntity: TournamentGroup::class)]
    #[ORM\JoinColumn(name: 'group_id', nullable: true, onDelete: 'SET NULL')]
    private ?TournamentGroup $group = null;

    /**
     * @var Collection<int, MatchSubmission>
     */
    #[ORM\OneToMany(targetEntity: MatchSubmission::class, mappedBy: 'match', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $submissions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->submissions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRound(): Round
    {
        return $this->round;
    }

    public function setRound(Round $round): self
    {
        $this->round = $round;

        return $this;
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
     * Check if this match belongs to a group (for group stage).
     */
    public function hasGroup(): bool
    {
        return $this->group !== null;
    }

    public function getPlayer1(): Registration
    {
        return $this->player1;
    }

    public function setPlayer1(Registration $player1): self
    {
        $this->player1 = $player1;

        return $this;
    }

    public function getPlayer2(): ?Registration
    {
        return $this->player2;
    }

    public function setPlayer2(?Registration $player2): self
    {
        $this->player2 = $player2;

        return $this;
    }

    public function getTableNumber(): int
    {
        return $this->tableNumber;
    }

    public function setTableNumber(int $tableNumber): self
    {
        $this->tableNumber = $tableNumber;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResult(): ?array
    {
        return $this->result;
    }

    /**
     * @param array<string, mixed>|null $result
     */
    public function setResult(?array $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getStatus(): MatchStatus
    {
        return $this->status;
    }

    public function setStatus(MatchStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isBye(): bool
    {
        return $this->isBye;
    }

    public function setIsBye(bool $isBye): self
    {
        $this->isBye = $isBye;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
     * @return array<int, array{timestamp: string, type: string, data: array<string, mixed>}>|null
     */
    public function getDisputeHistory(): ?array
    {
        return $this->disputeHistory;
    }

    /**
     * Add an entry to the dispute history.
     *
     * @param string               $type Event type: 'created', 'resolved', 'submission'
     * @param array<string, mixed> $data Event data
     */
    public function addDisputeHistoryEntry(string $type, array $data): self
    {
        if ($this->disputeHistory === null) {
            $this->disputeHistory = [];
        }

        $this->disputeHistory[] = [
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'type' => $type,
            'data' => $data,
        ];

        return $this;
    }

    /**
     * Clear dispute history.
     */
    public function clearDisputeHistory(): self
    {
        $this->disputeHistory = null;

        return $this;
    }

    /**
     * @return Collection<int, MatchSubmission>
     */
    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function addSubmission(MatchSubmission $submission): self
    {
        if (!$this->submissions->contains($submission)) {
            $this->submissions->add($submission);
            $submission->setMatch($this);
        }

        return $this;
    }

    public function removeSubmission(MatchSubmission $submission): self
    {
        $this->submissions->removeElement($submission);

        return $this;
    }

    /**
     * Clear all submissions (useful when resolving disputes).
     */
    public function clearSubmissions(): self
    {
        $this->submissions->clear();

        return $this;
    }

    /**
     * Get submission count.
     */
    public function getSubmissionCount(): int
    {
        return $this->submissions->count();
    }

    /**
     * Check if both players have submitted results.
     */
    public function hasBothSubmissions(): bool
    {
        return $this->submissions->count() >= 2;
    }

    /**
     * Check if this is a BYE match (player2 is null or isBye flag is set).
     */
    public function isByeMatch(): bool
    {
        return $this->isBye || $this->player2 === null;
    }

    /**
     * Check if match is pending.
     */
    public function isPending(): bool
    {
        return $this->status === MatchStatus::PENDING;
    }

    /**
     * Check if match is ongoing.
     */
    public function isOngoing(): bool
    {
        return $this->status === MatchStatus::ONGOING;
    }

    /**
     * Check if match is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === MatchStatus::COMPLETED;
    }

    /**
     * Check if match is disputed.
     */
    public function isDisputed(): bool
    {
        return $this->status === MatchStatus::DISPUTE;
    }

    /**
     * Assign a BYE to player1 (auto-win).
     * Score is 1-0 for BO1, 2-0 for BO3.
     */
    public function assignBye(): self
    {
        $this->isBye = true;
        $this->player2 = null;
        $this->status = MatchStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        $format = $this->round->getTournament()->getMatchFormatForRound($this->round);
        $byeScore = $format === MatchFormat::BO1 ? 1 : 2;

        $this->result = [
            'winnerId' => $this->player1->getId(),
            'player1Score' => $byeScore,
            'player2Score' => 0,
            'isBye' => true,
        ];

        return $this;
    }

    /**
     * Prepare a BYE match without completing it.
     *
     * Used when the round is still PENDING. The BYE will be
     * completed when the round starts.
     */
    public function prepareBye(): self
    {
        $this->isBye = true;
        $this->player2 = null;
        // Keep status as PENDING - will be completed when round starts

        return $this;
    }

    /**
     * Complete a prepared BYE match.
     *
     * Called when the round starts to finalize BYE results.
     */
    public function completeBye(): self
    {
        if (!$this->isBye) {
            return $this;
        }

        $this->status = MatchStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        $format = $this->round->getTournament()->getMatchFormatForRound($this->round);
        $byeScore = $format === MatchFormat::BO1 ? 1 : 2;

        $this->result = [
            'winnerId' => $this->player1->getId(),
            'player1Score' => $byeScore,
            'player2Score' => 0,
            'isBye' => true,
        ];

        return $this;
    }

    /**
     * Get the winner registration based on result.
     */
    public function getWinner(): ?Registration
    {
        if ($this->result === null || !isset($this->result['winnerId'])) {
            return null;
        }

        $winnerId = $this->result['winnerId'];

        if ($this->player1->getId() === $winnerId) {
            return $this->player1;
        }

        if ($this->player2 !== null && $this->player2->getId() === $winnerId) {
            return $this->player2;
        }

        return null;
    }

    /**
     * Get the loser registration based on result.
     */
    public function getLoser(): ?Registration
    {
        if ($this->result === null || !isset($this->result['winnerId']) || $this->isByeMatch()) {
            return null;
        }

        $winnerId = $this->result['winnerId'];

        if ($this->player1->getId() === $winnerId) {
            return $this->player2;
        }

        return $this->player1;
    }

    /**
     * Get player1's score from result.
     */
    public function getPlayer1Score(): int
    {
        return $this->result['player1Score'] ?? 0;
    }

    /**
     * Get player2's score from result.
     */
    public function getPlayer2Score(): int
    {
        return $this->result['player2Score'] ?? 0;
    }

    /**
     * Check if a registration is involved in this match.
     */
    public function hasPlayer(Registration $registration): bool
    {
        return $this->player1 === $registration
            || ($this->player2 !== null && $this->player2 === $registration);
    }

    /**
     * Get the opponent of a given registration.
     */
    public function getOpponent(Registration $registration): ?Registration
    {
        if ($this->player1 === $registration) {
            return $this->player2;
        }

        if ($this->player2 === $registration) {
            return $this->player1;
        }

        return null;
    }

    /**
     * Complete the match with result.
     *
     * @param array<string, mixed> $result
     */
    public function complete(array $result): self
    {
        $this->result = $result;
        $this->status = MatchStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Mark match as disputed.
     */
    public function dispute(): self
    {
        $this->status = MatchStatus::DISPUTE;

        return $this;
    }
}
