<?php

declare(strict_types=1);

namespace App\Service\OAuth;

class OAuthFeatureService
{
    public const PROVIDER_ALTERED_REUNION = 'altered_reunion';

    public function __construct(
        private readonly bool $alteredReunionEnabled,
    ) {
    }

    /**
     * Check if Altered Re:Union OAuth is enabled.
     */
    public function isAlteredReunionEnabled(): bool
    {
        return $this->alteredReunionEnabled;
    }

    /**
     * Check if a specific OAuth provider is enabled.
     */
    public function isProviderEnabled(string $provider): bool
    {
        return match ($provider) {
            self::PROVIDER_ALTERED_REUNION => $this->alteredReunionEnabled,
            default => false,
        };
    }

    /**
     * Get list of all enabled OAuth providers.
     *
     * @return array<string>
     */
    public function getEnabledProviders(): array
    {
        $providers = [];

        if ($this->alteredReunionEnabled) {
            $providers[] = self::PROVIDER_ALTERED_REUNION;
        }

        return $providers;
    }
}
