<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth;

use App\Service\OAuth\OAuthFeatureService;
use PHPUnit\Framework\TestCase;

final class OAuthFeatureServiceTest extends TestCase
{
    public function testIsAlteredReunionEnabledReturnsTrue(): void
    {
        $service = new OAuthFeatureService(alteredReunionEnabled: true);

        $this->assertTrue($service->isAlteredReunionEnabled());
    }

    public function testIsAlteredReunionEnabledReturnsFalse(): void
    {
        $service = new OAuthFeatureService(alteredReunionEnabled: false);

        $this->assertFalse($service->isAlteredReunionEnabled());
    }

    public function testIsProviderEnabledReturnsCorrectValue(): void
    {
        $serviceEnabled = new OAuthFeatureService(alteredReunionEnabled: true);
        $serviceDisabled = new OAuthFeatureService(alteredReunionEnabled: false);

        $this->assertTrue($serviceEnabled->isProviderEnabled('altered_reunion'));
        $this->assertFalse($serviceDisabled->isProviderEnabled('altered_reunion'));
    }

    public function testIsProviderEnabledReturnsFalseForUnknownProvider(): void
    {
        $service = new OAuthFeatureService(alteredReunionEnabled: true);

        $this->assertFalse($service->isProviderEnabled('unknown_provider'));
    }

    public function testGetEnabledProvidersReturnsAlteredReunionWhenEnabled(): void
    {
        $service = new OAuthFeatureService(alteredReunionEnabled: true);

        $providers = $service->getEnabledProviders();

        $this->assertContains('altered_reunion', $providers);
    }

    public function testGetEnabledProvidersReturnsEmptyWhenDisabled(): void
    {
        $service = new OAuthFeatureService(alteredReunionEnabled: false);

        $providers = $service->getEnabledProviders();

        $this->assertEmpty($providers);
    }

    public function testProviderConstant(): void
    {
        $this->assertSame('altered_reunion', OAuthFeatureService::PROVIDER_ALTERED_REUNION);
    }
}
