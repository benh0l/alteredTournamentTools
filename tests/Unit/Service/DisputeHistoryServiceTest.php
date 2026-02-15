<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentMatchRepository;
use App\Service\DisputeHistoryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisputeHistoryServiceTest extends TestCase
{
    private DisputeHistoryService $service;
    private TournamentMatchRepository&MockObject $matchRepository;
    private RegistrationRepository&MockObject $registrationRepository;

    protected function setUp(): void
    {
        $this->matchRepository = $this->createMock(TournamentMatchRepository::class);
        $this->registrationRepository = $this->createMock(RegistrationRepository::class);

        $this->service = new DisputeHistoryService(
            $this->matchRepository,
            $this->registrationRepository
        );
    }

    public function testGetDisputeSummaryForUserWithNoRegistrations(): void
    {
        $user = $this->createMockUser(1);

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([]);

        $summary = $this->service->getDisputeSummaryForUser($user);

        $this->assertSame(0, $summary['totalDisputes']);
        $this->assertSame(0, $summary['resolvedInFavor']);
        $this->assertSame(0, $summary['resolvedAgainst']);
        $this->assertEmpty($summary['matches']);
    }

    public function testGetDisputeSummaryForUserWithDisputes(): void
    {
        $user = $this->createMockUser(1);
        $tournament = $this->createTournament($user);
        $registration = new Registration($tournament, $user);

        $reflection = new \ReflectionProperty(Registration::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($registration, 1);

        // Create a disputed match
        $match = $this->createDisputedMatch($registration);

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$registration]);

        $this->matchRepository
            ->method('findDisputedMatchesForPlayer')
            ->willReturn([$match]);

        $summary = $this->service->getDisputeSummaryForUser($user);

        $this->assertSame(1, $summary['totalDisputes']);
        $this->assertCount(1, $summary['matches']);
    }

    public function testGetDisputeSummaryForRegistration(): void
    {
        $user = $this->createMockUser(1);
        $tournament = $this->createTournament($user);
        $registration = new Registration($tournament, $user);

        $match = $this->createDisputedMatch($registration);

        $this->matchRepository
            ->method('findDisputedMatchesForPlayer')
            ->willReturn([$match]);

        $summary = $this->service->getDisputeSummaryForRegistration($registration);

        $this->assertSame(1, $summary['totalDisputes']);
        $this->assertCount(1, $summary['matches']);
        $this->assertSame($match, $summary['matches'][0]);
    }

    public function testCheckDisputePatternNotFlaggedWithFewMatches(): void
    {
        $user = $this->createMockUser(1);
        $tournament = $this->createTournament($user);
        $registration = new Registration($tournament, $user);

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$registration]);

        $this->matchRepository
            ->method('findByPlayer')
            ->willReturn([]);

        $this->matchRepository
            ->method('findDisputedMatchesForPlayer')
            ->willReturn([]);

        $result = $this->service->checkDisputePattern($user);

        $this->assertFalse($result['isFlagged']);
        $this->assertNull($result['reason']);
        $this->assertSame(0, $result['stats']['total']);
        $this->assertSame(0.0, $result['stats']['ratio']);
    }

    public function testCheckDisputePatternFlaggedWithHighRatio(): void
    {
        $user = $this->createMockUser(1);
        $tournament = $this->createTournament($user);
        $registration = new Registration($tournament, $user);

        // Create 10 matches
        $matches = [];
        for ($i = 0; $i < 10; $i++) {
            $matches[] = new TournamentMatch();
        }

        // Create 3 disputed matches (30% ratio)
        $disputedMatches = [];
        for ($i = 0; $i < 3; $i++) {
            $disputedMatches[] = new TournamentMatch();
        }

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$registration]);

        $this->matchRepository
            ->method('findByPlayer')
            ->willReturn($matches);

        $this->matchRepository
            ->method('findDisputedMatchesForPlayer')
            ->willReturn($disputedMatches);

        $result = $this->service->checkDisputePattern($user);

        $this->assertTrue($result['isFlagged']);
        $this->assertNotNull($result['reason']);
        $this->assertStringContainsString('30%', $result['reason']);
        $this->assertSame(3, $result['stats']['total']);
        $this->assertSame(0.3, $result['stats']['ratio']);
    }

    public function testCheckDisputePatternNotFlaggedWithLowRatio(): void
    {
        $user = $this->createMockUser(1);
        $tournament = $this->createTournament($user);
        $registration = new Registration($tournament, $user);

        // Create 20 matches
        $matches = [];
        for ($i = 0; $i < 20; $i++) {
            $matches[] = new TournamentMatch();
        }

        // Create 2 disputed matches (10% ratio - below threshold)
        $disputedMatches = [];
        for ($i = 0; $i < 2; $i++) {
            $disputedMatches[] = new TournamentMatch();
        }

        $this->registrationRepository
            ->method('findBy')
            ->willReturn([$registration]);

        $this->matchRepository
            ->method('findByPlayer')
            ->willReturn($matches);

        $this->matchRepository
            ->method('findDisputedMatchesForPlayer')
            ->willReturn($disputedMatches);

        $result = $this->service->checkDisputePattern($user);

        $this->assertFalse($result['isFlagged']);
        $this->assertNull($result['reason']);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function createMockUser(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getPseudo')->willReturn("Player{$id}");

        return $user;
    }

    private function createTournament(User $organizer): Tournament
    {
        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);

        return $tournament;
    }

    private function createDisputedMatch(Registration $player): TournamentMatch
    {
        $opponent = $this->createMock(Registration::class);
        $opponentUser = $this->createMockUser(99);
        $opponent->method('getPlayer')->willReturn($opponentUser);

        $round = new Round();
        $round->setTournament($player->getTournament());
        $round->setRoundNumber(1);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($player);
        $match->setPlayer2($opponent);
        $match->setTableNumber(1);
        $match->setStatus(MatchStatus::DISPUTE);
        $match->addDisputeHistoryEntry('created', ['reason' => 'conflicting_submissions']);

        return $match;
    }
}
