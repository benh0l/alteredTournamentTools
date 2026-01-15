<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TournamentGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TournamentGroupRepository::class)]
#[ORM\Table(name: 'tournament_groups')]
#[ORM\UniqueConstraint(name: 'uniq_group_tournament_letter', columns: ['tournament_id', 'group_letter'])]
#[ORM\Index(name: 'idx_tournament_groups_tournament', columns: ['tournament_id'])]
class TournamentGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tournament::class, inversedBy: 'groups')]
    #[ORM\JoinColumn(name: 'tournament_id', nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\Column(name: 'group_name', type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Le nom de la poule est requis')]
    #[Assert\Length(max: 50)]
    private string $name;

    #[ORM\Column(name: 'group_letter', type: 'string', length: 1)]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 1)]
    private string $letter;

    /** @var Collection<int, Registration> */
    #[ORM\ManyToMany(targetEntity: Registration::class)]
    #[ORM\JoinTable(name: 'tournament_group_players')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'registration_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $players;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLetter(): string
    {
        return $this->letter;
    }

    public function setLetter(string $letter): self
    {
        $this->letter = strtoupper($letter);

        return $this;
    }

    /**
     * @return Collection<int, Registration>
     */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Registration $registration): self
    {
        if (!$this->players->contains($registration)) {
            $this->players->add($registration);
        }

        return $this;
    }

    public function removePlayer(Registration $registration): self
    {
        $this->players->removeElement($registration);

        return $this;
    }

    public function getPlayerCount(): int
    {
        return $this->players->count();
    }

    public function hasPlayer(Registration $registration): bool
    {
        return $this->players->contains($registration);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get the number of rounds needed for round-robin in this group.
     * For N players: N-1 rounds (or N if odd, accounting for bye).
     */
    public function getRequiredRounds(): int
    {
        $playerCount = $this->getPlayerCount();

        if ($playerCount <= 1) {
            return 0;
        }

        // For odd number of players, we need N rounds (each player gets a bye once)
        // For even number of players, we need N-1 rounds
        return $playerCount % 2 === 0 ? $playerCount - 1 : $playerCount;
    }

    /**
     * Get total number of matches in round-robin for this group.
     * Formula: n(n-1)/2 where n = number of players.
     */
    public function getTotalMatches(): int
    {
        $playerCount = $this->getPlayerCount();

        if ($playerCount <= 1) {
            return 0;
        }

        return ($playerCount * ($playerCount - 1)) / 2;
    }
}
