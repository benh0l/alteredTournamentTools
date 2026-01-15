<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\GeocodingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests for GeocodingService.
 */
final class GeocodingServiceTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private CacheInterface $cache;
    private GeocodingService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache = new ArrayAdapter();
        $this->service = new GeocodingService(
            $this->httpClient,
            $this->cache,
            new NullLogger()
        );
    }

    public function testGeocodeReturnsCoordinatesForValidAddress(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            [
                'lat' => '48.8566',
                'lon' => '2.3522',
                'display_name' => 'Paris, France',
            ],
        ]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $result = $this->service->geocode('Paris');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(48.8566, $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(2.3522, $result['lng'], 0.0001);
    }

    public function testGeocodeReturnsNullForEmptyAddress(): void
    {
        $result = $this->service->geocode('');

        $this->assertNull($result);
    }

    public function testGeocodeReturnsNullForNotFoundAddress(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $result = $this->service->geocode('xyznonexistentlocation123');

        $this->assertNull($result);
    }

    public function testGeocodeCachesResults(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            [
                'lat' => '48.8566',
                'lon' => '2.3522',
            ],
        ]);

        // Should only be called once due to caching
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        // First call - goes to API
        $result1 = $this->service->geocode('Paris');

        // Second call - should come from cache
        $result2 = $this->service->geocode('Paris');

        $this->assertEquals($result1, $result2);
    }

    public function testGeocodeTrimsWhitespace(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            [
                'lat' => '48.8566',
                'lon' => '2.3522',
            ],
        ]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $result = $this->service->geocode('  Paris  ');

        $this->assertNotNull($result);
    }

    public function testReverseGeocodeReturnsAddress(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'display_name' => '10 Rue de Rivoli, 75001 Paris, France',
        ]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $result = $this->service->reverseGeocode(48.8566, 2.3522);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Paris', $result);
    }

    public function testReverseGeocodeCachesResults(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'display_name' => 'Paris, France',
        ]);

        // Should only be called once
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result1 = $this->service->reverseGeocode(48.8566, 2.3522);
        $result2 = $this->service->reverseGeocode(48.8566, 2.3522);

        $this->assertEquals($result1, $result2);
    }
}
