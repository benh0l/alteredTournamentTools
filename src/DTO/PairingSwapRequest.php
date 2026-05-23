<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for requesting a player swap between tables.
 */
final class PairingSwapRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Le joueur 1 est requis.')]
        #[Assert\Positive(message: 'L\'ID du joueur 1 doit etre positif.')]
        public readonly int $player1Id,

        #[Assert\NotNull(message: 'Le joueur 2 est requis.')]
        #[Assert\Positive(message: 'L\'ID du joueur 2 doit etre positif.')]
        public readonly int $player2Id,

        /**
         * When true, the swap will be performed even if it creates a rematch.
         */
        public readonly bool $forceSwap = false,
    ) {
    }

    /**
     * Create from array (JSON request body).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            player1Id: (int) ($data['player1Id'] ?? 0),
            player2Id: (int) ($data['player2Id'] ?? 0),
            forceSwap: (bool) ($data['forceSwap'] ?? false),
        );
    }
}
