<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Async message to send email notification when a player is removed from a tournament.
 */
final readonly class SendRegistrationRemovalMessage
{
    public function __construct(
        private string $playerEmail,
        private string $playerPseudo,
        private string $tournamentName,
        private int $tournamentId,
        private ?string $reason = null
    ) {
    }

    public function getPlayerEmail(): string
    {
        return $this->playerEmail;
    }

    public function getPlayerPseudo(): string
    {
        return $this->playerPseudo;
    }

    public function getTournamentName(): string
    {
        return $this->tournamentName;
    }

    public function getTournamentId(): int
    {
        return $this->tournamentId;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
