<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for requesting a manual BYE assignment.
 */
final class AssignByeRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Le joueur est requis.')]
        #[Assert\Positive(message: 'L\'ID du joueur doit etre positif.')]
        public readonly int $playerId,
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
            playerId: (int) ($data['playerId'] ?? 0),
        );
    }
}
