<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when a registration's team composition is not valid for the tournament.
 */
final class InvalidTeamException extends \DomainException
{
    public const PARTNER_REQUIRED = 'partner_required';
    public const PARTNER_NOT_ALLOWED = 'partner_not_allowed';
    public const PARTNER_IS_PLAYER = 'partner_is_player';
    public const PARTNER_ALREADY_REGISTERED = 'partner_already_registered';
    public const PARTNER_NOT_FOUND = 'partner_not_found';

    private function __construct(private readonly string $reason)
    {
        parent::__construct(sprintf('Invalid team: %s', $reason));
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getTranslationKey(): string
    {
        return 'flash.error.team.' . $this->reason;
    }
}
