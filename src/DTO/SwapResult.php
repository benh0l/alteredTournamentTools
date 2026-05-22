<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result of a player swap operation.
 */
final class SwapResult
{
    /**
     * @param array{player1: string, player2: string, previousRound: int}|null $rematchDetails
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $warning = null,
        public readonly bool $rematchDetected = false,
        public readonly ?array $rematchDetails = null,
    ) {
    }

    /**
     * Create a successful result.
     */
    public static function success(): self
    {
        return new self(
            success: true,
            warning: null,
            rematchDetected: false,
            rematchDetails: null,
        );
    }

    /**
     * Create a rematch warning result.
     *
     * @param array{player1: string, player2: string, previousRound: int} $rematchDetails
     */
    public static function rematchWarning(array $rematchDetails): self
    {
        return new self(
            success: false,
            warning: 'rematch_detected',
            rematchDetected: true,
            rematchDetails: $rematchDetails,
        );
    }

    /**
     * Convert to array for JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'warning' => $this->warning,
            'rematchDetected' => $this->rematchDetected,
            'rematchDetails' => $this->rematchDetails,
        ];
    }
}
