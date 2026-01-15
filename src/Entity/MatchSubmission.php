<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MatchSubmissionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a result submission by a player for a match.
 *
 * Each player submits their view of the result independently.
 * When both players submit matching results, the match is validated.
 * When submissions conflict, a dispute is created.
 */
#[ORM\Entity(repositoryClass: MatchSubmissionRepository::class)]
#[ORM\Table(name: 'match_submissions')]
#[ORM\Index(name: 'idx_submissions_match', columns: ['match_id'])]
#[ORM\Index(name: 'idx_submissions_submitted_by', columns: ['submitted_by_id'])]
#[ORM\UniqueConstraint(name: 'uq_submission_match_player', columns: ['match_id', 'submitted_by_id'])]
class MatchSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TournamentMatch::class, inversedBy: 'submissions')]
    #[ORM\JoinColumn(name: 'match_id', nullable: false, onDelete: 'CASCADE')]
    private TournamentMatch $match;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'submitted_by_id', nullable: false)]
    private User $submittedBy;

    /**
     * The registration ID of the claimed winner.
     * Null indicates a tie/draw.
     */
    #[ORM\Column(name: 'claimed_winner_id', type: 'integer', nullable: true)]
    private ?int $claimedWinnerId = null;

    /**
     * Score for player 1 (2-0, 2-1 for BO3).
     */
    #[ORM\Column(name: 'player1_score', type: 'integer')]
    private int $player1Score;

    /**
     * Score for player 2 (2-0, 2-1 for BO3).
     */
    #[ORM\Column(name: 'player2_score', type: 'integer')]
    private int $player2Score;

    /**
     * Game-by-game results for BO3 matches.
     * Format: [{"winner": "player1"}, {"winner": "player2"}, {"winner": "player1"}]
     *
     * @var array<int, array{winner: string}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $gameDetails = null;

    #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $submittedAt;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatch(): TournamentMatch
    {
        return $this->match;
    }

    public function setMatch(TournamentMatch $match): self
    {
        $this->match = $match;

        return $this;
    }

    public function getSubmittedBy(): User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(User $submittedBy): self
    {
        $this->submittedBy = $submittedBy;

        return $this;
    }

    public function getClaimedWinnerId(): ?int
    {
        return $this->claimedWinnerId;
    }

    public function setClaimedWinnerId(?int $claimedWinnerId): self
    {
        $this->claimedWinnerId = $claimedWinnerId;

        return $this;
    }

    /**
     * Check if this submission declares a tie.
     */
    public function isTie(): bool
    {
        return $this->claimedWinnerId === null;
    }

    public function getPlayer1Score(): int
    {
        return $this->player1Score;
    }

    public function setPlayer1Score(int $player1Score): self
    {
        $this->player1Score = $player1Score;

        return $this;
    }

    public function getPlayer2Score(): int
    {
        return $this->player2Score;
    }

    public function setPlayer2Score(int $player2Score): self
    {
        $this->player2Score = $player2Score;

        return $this;
    }

    /**
     * @return array<int, array{winner: string}>|null
     */
    public function getGameDetails(): ?array
    {
        return $this->gameDetails;
    }

    /**
     * @param array<int, array{winner: string}>|null $gameDetails
     */
    public function setGameDetails(?array $gameDetails): self
    {
        $this->gameDetails = $gameDetails;

        return $this;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    /**
     * Check if this submission claims player submitted as the winner.
     */
    public function claimsSubmitterWon(): bool
    {
        // Tie means no one won
        if ($this->claimedWinnerId === null) {
            return false;
        }

        $submitterRegistration = $this->findSubmitterRegistration();
        if ($submitterRegistration === null) {
            return false;
        }

        return $this->claimedWinnerId === $submitterRegistration->getId();
    }

    /**
     * Get the registration of the player who submitted this result.
     */
    public function findSubmitterRegistration(): ?Registration
    {
        $player1 = $this->match->getPlayer1();
        $player2 = $this->match->getPlayer2();

        if ($player1->getPlayer() === $this->submittedBy) {
            return $player1;
        }

        if ($player2 !== null && $player2->getPlayer() === $this->submittedBy) {
            return $player2;
        }

        return null;
    }

    /**
     * Check if this submission agrees with another submission.
     */
    public function agreesWith(MatchSubmission $other): bool
    {
        return $this->claimedWinnerId === $other->getClaimedWinnerId()
            && $this->player1Score === $other->getPlayer1Score()
            && $this->player2Score === $other->getPlayer2Score();
    }

    /**
     * Create result array for match completion.
     *
     * @return array<string, mixed>
     */
    public function toResultArray(): array
    {
        return [
            'winnerId' => $this->claimedWinnerId,
            'player1Score' => $this->player1Score,
            'player2Score' => $this->player2Score,
            'gameDetails' => $this->gameDetails,
        ];
    }
}
