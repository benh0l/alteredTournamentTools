<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TournamentRepository logic.
 *
 * Note: These tests focus on the Haversine calculation logic.
 * Full repository tests would require database integration.
 */
final class TournamentRepositoryTest extends TestCase
{
    /**
     * Test Haversine distance calculation.
     */
    public function testHaversineDistanceCalculation(): void
    {
        // Paris to Lyon is approximately 392 km
        $parisLat = 48.8566;
        $parisLng = 2.3522;
        $lyonLat = 45.7640;
        $lyonLng = 4.8357;

        $distance = $this->calculateHaversineDistance($parisLat, $parisLng, $lyonLat, $lyonLng);

        // Should be approximately 392 km (allow 10% tolerance)
        $this->assertGreaterThan(350, $distance);
        $this->assertLessThan(430, $distance);
    }

    public function testHaversineDistanceSamePointIsZero(): void
    {
        $lat = 48.8566;
        $lng = 2.3522;

        $distance = $this->calculateHaversineDistance($lat, $lng, $lat, $lng);

        $this->assertEquals(0, $distance);
    }

    public function testHaversineDistanceShortDistance(): void
    {
        // Paris Eiffel Tower to Arc de Triomphe is approximately 3.5 km
        $eiffelLat = 48.8584;
        $eiffelLng = 2.2945;
        $arcLat = 48.8738;
        $arcLng = 2.2950;

        $distance = $this->calculateHaversineDistance($eiffelLat, $eiffelLng, $arcLat, $arcLng);

        // Should be approximately 1.7 km
        $this->assertGreaterThan(1, $distance);
        $this->assertLessThan(3, $distance);
    }

    public function testTournamentCoordinatesMethods(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setPseudo('Test');
        $user->setPassword('password');

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($user);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);

        // Initially no coordinates
        $this->assertFalse($tournament->hasCoordinates());
        $this->assertNull($tournament->getLatitude());
        $this->assertNull($tournament->getLongitude());

        // Set coordinates
        $tournament->setCoordinates(48.8566, 2.3522);

        $this->assertTrue($tournament->hasCoordinates());
        $this->assertEqualsWithDelta(48.8566, $tournament->getLatitude(), 0.0001);
        $this->assertEqualsWithDelta(2.3522, $tournament->getLongitude(), 0.0001);
    }

    public function testTournamentCoordinatesCanBeCleared(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setPseudo('Test');
        $user->setPassword('password');

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($user);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);

        $tournament->setCoordinates(48.8566, 2.3522);
        $this->assertTrue($tournament->hasCoordinates());

        $tournament->setCoordinates(null, null);
        $this->assertFalse($tournament->hasCoordinates());
    }

    /**
     * Haversine formula implementation (same as in repository).
     */
    private function calculateHaversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * asin(sqrt($a));

        return $earthRadius * $c;
    }
}
