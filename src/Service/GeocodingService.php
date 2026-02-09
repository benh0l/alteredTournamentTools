<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for geocoding addresses to coordinates (FR59).
 *
 * Uses Nominatim (OpenStreetMap) API for free geocoding.
 * Results are cached in Redis for 24 hours to reduce API calls.
 */
class GeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    private const CACHE_TTL = 86400; // 24 hours

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Geocode an address to coordinates.
     *
     * @param string $address Address, city name, or postal code
     *
     * @return array{lat: float, lng: float}|null Coordinates or null if not found
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if (empty($address)) {
            return null;
        }

        $cacheKey = 'geocode_' . md5(strtolower($address));

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($address) {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->fetchFromNominatim($address);
            });
        } catch (\Exception $e) {
            $this->logger->error('Geocoding cache error', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            // Try direct fetch on cache error
            return $this->fetchFromNominatim($address);
        }
    }

    /**
     * Fetch coordinates from Nominatim API.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function fetchFromNominatim(string $address): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::NOMINATIM_URL, [
                'query' => [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'fr,be,ch,lu,mc', // France + neighbors
                ],
                'headers' => [
                    'User-Agent' => 'AlteredTournamentTools/1.0 (contact@example.com)',
                ],
            ]);

            $data = $response->toArray();

            if (empty($data)) {
                $this->logger->info('Geocoding: No results found');

                return null;
            }

            $result = $data[0];

            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Nominatim API error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Reverse geocode coordinates to an address.
     *
     * @return string|null Address or null if not found
     */
    public function reverseGeocode(float $lat, float $lng): ?string
    {
        $cacheKey = 'reverse_geocode_' . md5("{$lat},{$lng}");

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($lat, $lng) {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->fetchReverseFromNominatim($lat, $lng);
            });
        } catch (\Exception $e) {
            $this->logger->error('Reverse geocoding cache error', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);

            return $this->fetchReverseFromNominatim($lat, $lng);
        }
    }

    /**
     * Fetch address from coordinates via Nominatim.
     */
    private function fetchReverseFromNominatim(float $lat, float $lng): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
                'query' => [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                ],
                'headers' => [
                    'User-Agent' => 'AlteredTournamentTools/1.0 (contact@example.com)',
                ],
            ]);

            $data = $response->toArray();

            return $data['display_name'] ?? null;
        } catch (\Exception $e) {
            $this->logger->error('Nominatim reverse API error', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
