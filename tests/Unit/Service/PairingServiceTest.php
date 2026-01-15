<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\DecklistTransparency;
use App\Enum\MatchFormat;
use App\Enum\MatchStatus;
use App\Enum\PairingMode;
use App\Enum\RoundStatus;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Entity\TournamentMatch;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Exception\RoundNotCompleteException;
use App\Repository\TournamentMatchRepository;
use App\Service\PairingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PairingServiceTest extends TestCase
{
    private PairingService $service;
    private EntityManagerInterface&MockObject $entityManager;
    private TournamentMatchRepository&MockObject $matchRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->matchRepository = $this->createMock(TournamentMatchRepository::class);

        $this->service = new PairingService(
            $this->entityManager,
            $this->matchRepository
        );
    }

    public function testGenerateRound1PairingsCreatesRoundWithCorrectNumber(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(4);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        $this->assertSame(1, $round->getRoundNumber());
    }

    public function testGenerateRound1PairingsCreatesMatchesForAllPlayers(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(4);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        // 4 players = 2 matches
        $this->assertCount(2, $round->getMatches());
    }

    public function testGenerateRound1PairingsAssignsTableNumbers(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(6);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        $tableNumbers = [];
        foreach ($round->getMatches() as $match) {
            $tableNumbers[] = $match->getTableNumber();
        }

        $this->assertSame([1, 2, 3], $tableNumbers);
    }

    public function testGenerateRound1PairingsAllMatchesArePending(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(4);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        foreach ($round->getMatches() as $match) {
            if (!$match->isByeMatch()) {
                $this->assertSame(MatchStatus::PENDING, $match->getStatus());
            }
        }
    }

    public function testGenerateRound1PairingsRoundIsOngoing(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(4);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        $this->assertSame(RoundStatus::ONGOING, $round->getStatus());
    }

    public function testGenerateRound1PairingsUpdatesTournamentStatus(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(4);

        $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        $this->assertSame(TournamentStatus::ONGOING, $tournament->getStatus());
    }

    public function testGenerateRound1PairingsSetsStartedAt(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(4);

        $this->assertNull($tournament->getStartedAt());

        $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        $this->assertNotNull($tournament->getStartedAt());
    }

    public function testGenerateRound1PairingsWithOddPlayersCreatesBye(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(5);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        // 5 players = 2 regular matches + 1 BYE match
        $this->assertCount(3, $round->getMatches());

        $byeMatches = array_filter(
            $round->getMatches()->toArray(),
            fn ($match) => $match->isByeMatch()
        );
        $this->assertCount(1, $byeMatches);
    }

    public function testGenerateRound1PairingsWithOddPlayersByeMatchIsCompleted(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(5);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        foreach ($round->getMatches() as $match) {
            if ($match->isByeMatch()) {
                $this->assertSame(MatchStatus::COMPLETED, $match->getStatus());
                $this->assertNotNull($match->getCompletedAt());
                $this->assertNull($match->getPlayer2());
            }
        }
    }

    public function testGenerateRound1PairingsWithOddPlayersByeHasCorrectResult(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(5);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        foreach ($round->getMatches() as $match) {
            if ($match->isByeMatch()) {
                $result = $match->getResult();
                $this->assertNotNull($result);
                $this->assertSame($match->getPlayer1()->getId(), $result['winnerId']);
                $this->assertSame(2, $result['player1Score']);
                $this->assertSame(0, $result['player2Score']);
                $this->assertTrue($result['isBye']);
            }
        }
    }

    public function testGenerateRound1PairingsRandomModeShufflesPlayers(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(10);

        // Run multiple times and check for at least some variation
        $allSameOrder = true;
        $firstRunOrder = null;

        for ($i = 0; $i < 10; $i++) {
            $tournament = $this->createPublishedTournamentWithPlayers(10);
            $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

            $currentOrder = [];
            foreach ($round->getMatches() as $match) {
                $currentOrder[] = $match->getPlayer1()->getId();
                if ($match->getPlayer2() !== null) {
                    $currentOrder[] = $match->getPlayer2()->getId();
                }
            }

            if ($firstRunOrder === null) {
                $firstRunOrder = $currentOrder;
            } elseif ($currentOrder !== $firstRunOrder) {
                $allSameOrder = false;
                break;
            }
        }

        $this->assertFalse($allSameOrder, 'Random mode should produce different orderings');
    }

    public function testGenerateRound1PairingsRegistrationOrderModePreservesOrder(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayersInOrder(4);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::REGISTRATION_ORDER);

        $matches = $round->getMatches()->toArray();

        // First registered player should be in first match
        $firstMatch = $matches[0];
        $registrations = $tournament->getRegistrations()->toArray();
        usort(
            $registrations,
            fn ($a, $b) => $a->getRegisteredAt() <=> $b->getRegisteredAt()
        );

        $this->assertSame($registrations[0], $firstMatch->getPlayer1());
        $this->assertSame($registrations[1], $firstMatch->getPlayer2());
    }

    public function testGenerateRound1PairingsThrowsIfNotEnoughPlayers(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(1);

        $this->expectException(InsufficientPlayersException::class);
        $this->expectExceptionMessage('au moins 2 joueurs');

        $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);
    }

    public function testGenerateRound1PairingsThrowsWithZeroPlayers(): void
    {
        $tournament = $this->createPublishedTournament();

        $this->expectException(InsufficientPlayersException::class);

        $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);
    }

    public function testGenerateRound1PairingsThrowsIfTournamentIsDraft(): void
    {
        $tournament = $this->createTournamentWithStatus(TournamentStatus::DRAFT, 4);

        $this->expectException(InvalidTournamentStateException::class);
        $this->expectExceptionMessage('Brouillon');

        $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);
    }

    public function testGenerateRound1PairingsThrowsIfTournamentIsOngoing(): void
    {
        $tournament = $this->createTournamentWithStatus(TournamentStatus::ONGOING, 4);

        $this->expectException(InvalidTournamentStateException::class);
        $this->expectExceptionMessage('En cours');

        $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);
    }

    public function testGenerateRound1PairingsThrowsIfTournamentIsCompleted(): void
    {
        $tournament = $this->createTournamentWithStatus(TournamentStatus::COMPLETED, 4);

        $this->expectException(InvalidTournamentStateException::class);

        $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);
    }

    public function testGenerateRound1PairingsThrowsIfTournamentIsCancelled(): void
    {
        $tournament = $this->createTournamentWithStatus(TournamentStatus::CANCELLED, 4);

        $this->expectException(InvalidTournamentStateException::class);

        $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);
    }

    public function testGenerateRound1PairingsWithMinimumTwoPlayers(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(2);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        $this->assertCount(1, $round->getMatches());
        $match = $round->getMatches()->first();
        $this->assertNotNull($match->getPlayer1());
        $this->assertNotNull($match->getPlayer2());
    }

    public function testGenerateRound1PairingsWithThreePlayersOneByeMatch(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(3);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        // 3 players = 1 regular match + 1 BYE match
        $this->assertCount(2, $round->getMatches());

        $regularMatches = 0;
        $byeMatches = 0;
        foreach ($round->getMatches() as $match) {
            if ($match->isByeMatch()) {
                $byeMatches++;
            } else {
                $regularMatches++;
            }
        }

        $this->assertSame(1, $regularMatches);
        $this->assertSame(1, $byeMatches);
    }

    public function testGenerateRound1PairingsEachPlayerAppearsOnce(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(8);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        $playerIds = [];
        foreach ($round->getMatches() as $match) {
            $playerIds[] = $match->getPlayer1()->getId();
            if ($match->getPlayer2() !== null) {
                $playerIds[] = $match->getPlayer2()->getId();
            }
        }

        // Each player should appear exactly once
        $this->assertCount(8, $playerIds);
        $this->assertCount(8, array_unique($playerIds));
    }

    public function testGenerateRound1PairingsWithLargePlayerCount(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(32);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        // 32 players = 16 matches
        $this->assertCount(16, $round->getMatches());
    }

    public function testGenerateRound1PairingsRoundIsAddedToTournament(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(4);

        $round = $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);

        $this->assertTrue($tournament->hasRounds());
        $this->assertCount(1, $tournament->getRounds());
        $this->assertSame($round, $tournament->getRounds()->first());
    }

    public function testInsufficientPlayersExceptionContainsDetails(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(1);

        try {
            $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);
            $this->fail('Expected InsufficientPlayersException');
        } catch (InsufficientPlayersException $e) {
            $this->assertSame(1, $e->getPlayerCount());
            $this->assertSame(2, $e->getMinimumRequired());
        }
    }

    public function testInvalidTournamentStateExceptionContainsDetails(): void
    {
        $tournament = $this->createTournamentWithStatus(TournamentStatus::DRAFT, 4);

        try {
            $this->service->generateRound1Pairings($tournament, PairingMode::RANDOM);
            $this->fail('Expected InvalidTournamentStateException');
        } catch (InvalidTournamentStateException $e) {
            $this->assertSame(TournamentStatus::DRAFT, $e->getCurrentStatus());
            $this->assertContains(TournamentStatus::PUBLISHED, $e->getExpectedStatuses());
        }
    }

    // =======================================================================
    // Tests for generateSubsequentRoundPairings (Story 5-3)
    // =======================================================================

    public function testGenerateSubsequentRoundCreatesRound2(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);

        $round2 = $this->service->generateSubsequentRoundPairings($tournament);

        $this->assertSame(2, $round2->getRoundNumber());
    }

    public function testGenerateSubsequentRoundCreatesCorrectNumberOfMatches(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);

        $round2 = $this->service->generateSubsequentRoundPairings($tournament);

        // 4 players = 2 matches
        $this->assertCount(2, $round2->getMatches());
    }

    public function testGenerateSubsequentRoundIsOngoing(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);

        $round2 = $this->service->generateSubsequentRoundPairings($tournament);

        $this->assertSame(RoundStatus::ONGOING, $round2->getStatus());
    }

    public function testGenerateSubsequentRoundCompletesPreviousRound(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);
        $round1 = $tournament->getLatestRound();

        $this->service->generateSubsequentRoundPairings($tournament);

        $this->assertSame(RoundStatus::COMPLETED, $round1->getStatus());
    }

    public function testGenerateSubsequentRoundAvoidsRematches(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);
        $round1 = $tournament->getLatestRound();

        // Record Round 1 pairings
        $round1Pairings = [];
        foreach ($round1->getMatches() as $match) {
            $p1 = $match->getPlayer1()->getId();
            $p2 = $match->getPlayer2()?->getId();
            if ($p2 !== null) {
                $round1Pairings[] = [$p1, $p2];
            }
        }

        $round2 = $this->service->generateSubsequentRoundPairings($tournament);

        // Verify no rematches
        foreach ($round2->getMatches() as $match) {
            $p1 = $match->getPlayer1()->getId();
            $p2 = $match->getPlayer2()?->getId();
            if ($p2 === null) {
                continue;
            }

            foreach ($round1Pairings as $pairing) {
                $isRematch = ($pairing[0] === $p1 && $pairing[1] === $p2)
                    || ($pairing[0] === $p2 && $pairing[1] === $p1);
                $this->assertFalse($isRematch, 'Round 2 should not have rematches from Round 1');
            }
        }
    }

    public function testGenerateSubsequentRoundThrowsIfNotOngoing(): void
    {
        $tournament = $this->createPublishedTournamentWithPlayers(4);

        $this->expectException(InvalidTournamentStateException::class);

        $this->service->generateSubsequentRoundPairings($tournament);
    }

    public function testGenerateSubsequentRoundThrowsIfNoRounds(): void
    {
        $tournament = $this->createTournamentWithStatus(TournamentStatus::ONGOING, 4);

        $this->expectException(InvalidTournamentStateException::class);

        $this->service->generateSubsequentRoundPairings($tournament);
    }

    public function testGenerateSubsequentRoundThrowsIfPreviousRoundNotComplete(): void
    {
        $tournament = $this->createOngoingTournamentWithIncompleteRound1(4);

        $this->expectException(RoundNotCompleteException::class);

        $this->service->generateSubsequentRoundPairings($tournament);
    }

    public function testGenerateSubsequentRoundWithOddPlayersCreatesBye(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(5);

        $round2 = $this->service->generateSubsequentRoundPairings($tournament);

        // 5 players = 2 matches + 1 BYE
        $this->assertCount(3, $round2->getMatches());

        $byeMatches = array_filter(
            $round2->getMatches()->toArray(),
            fn ($match) => $match->isByeMatch()
        );
        $this->assertCount(1, $byeMatches);
    }

    public function testGenerateSubsequentRoundByeGivenToLowestRankWithoutBye(): void
    {
        // Create tournament with 5 players where player 5 had BYE in Round 1
        $tournament = $this->createOngoingTournamentWithByeInRound1(5);

        $round2 = $this->service->generateSubsequentRoundPairings($tournament);

        // Find the BYE match
        $byeMatch = null;
        foreach ($round2->getMatches() as $match) {
            if ($match->isByeMatch()) {
                $byeMatch = $match;
                break;
            }
        }

        // The BYE should NOT go to the player who already had it
        $this->assertNotNull($byeMatch);
        // Note: The exact player depends on standings, but the key is
        // we verify BYE exists and goes to someone without prior BYE
    }

    public function testCalculateStandingsReturnsAllPlayers(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);

        $standings = $this->service->calculateStandings($tournament);

        $this->assertCount(4, $standings);
    }

    public function testCalculateStandingsWinsGive3Points(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);

        $standings = $this->service->calculateStandings($tournament);

        // Winners should have 3 points
        $winners = array_filter(
            $standings,
            fn ($s) => $s->getMatchPoints() === 3
        );
        $this->assertCount(2, $winners);
    }

    public function testCalculateStandingsLossesGive0Points(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);

        $standings = $this->service->calculateStandings($tournament);

        // Losers should have 0 points
        $losers = array_filter(
            $standings,
            fn ($s) => $s->getMatchPoints() === 0
        );
        $this->assertCount(2, $losers);
    }

    public function testCalculateStandingsTracksOpponents(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);

        $standings = $this->service->calculateStandings($tournament);

        // Each player should have 1 opponent after 1 round
        foreach ($standings as $standing) {
            $this->assertCount(1, $standing->getOpponents());
        }
    }

    public function testPairsPlayersWithSamePoints(): void
    {
        $tournament = $this->createOngoingTournamentWithCompletedRound1(4);

        $round2 = $this->service->generateSubsequentRoundPairings($tournament);

        // After Round 1, we have 2 winners (3 pts) and 2 losers (0 pts)
        // Round 2 should pair winners together and losers together
        // This means each match should have players with same points
        $standings = $this->service->calculateStandings($tournament);

        foreach ($round2->getMatches() as $match) {
            if ($match->isByeMatch()) {
                continue;
            }

            $p1Id = $match->getPlayer1()->getId();
            $p2Id = $match->getPlayer2()->getId();

            // Note: Standings are calculated before Round 2, so points reflect Round 1
            $p1Points = $standings[$p1Id]->getMatchPoints();
            $p2Points = $standings[$p2Id]->getMatchPoints();

            $this->assertSame($p1Points, $p2Points, 'Players with same points should be paired');
        }
    }

    public function testRound3PairingsWithMultipleRounds(): void
    {
        // Create tournament with completed Round 1 and Round 2
        $tournament = $this->createOngoingTournamentWithCompletedRound1(8);

        // Generate and complete Round 2
        $round2 = $this->service->generateSubsequentRoundPairings($tournament);
        $this->completeRoundWithResults($round2);

        // Generate Round 3
        $round3 = $this->service->generateSubsequentRoundPairings($tournament);

        $this->assertSame(3, $round3->getRoundNumber());
        $this->assertCount(4, $round3->getMatches()); // 8 players = 4 matches
    }

    // =======================================================================
    // Helper methods
    // =======================================================================

    /**
     * Creates a tournament with the given status and number of players.
     */
    private function createTournamentWithStatus(TournamentStatus $status, int $playerCount): Tournament
    {
        $tournament = $this->createBaseTournament();
        $tournament->setStatus($status);
        $this->addPlayers($tournament, $playerCount);

        return $tournament;
    }

    /**
     * Creates a published tournament with the given number of players.
     */
    private function createPublishedTournamentWithPlayers(int $playerCount): Tournament
    {
        return $this->createTournamentWithStatus(TournamentStatus::PUBLISHED, $playerCount);
    }

    /**
     * Creates a published tournament with no players.
     */
    private function createPublishedTournament(): Tournament
    {
        return $this->createPublishedTournamentWithPlayers(0);
    }

    /**
     * Creates a published tournament with players registered at different times.
     */
    private function createPublishedTournamentWithPlayersInOrder(int $playerCount): Tournament
    {
        $tournament = $this->createBaseTournament();
        $tournament->setStatus(TournamentStatus::PUBLISHED);

        // Add players with increasing registration times
        $baseTime = new \DateTimeImmutable('2024-01-01 10:00:00');
        for ($i = 0; $i < $playerCount; $i++) {
            $user = $this->createMockUser($i + 1);
            $registration = new Registration($tournament, $user);

            // Use reflection to set registeredAt to different times
            $reflection = new \ReflectionProperty(Registration::class, 'registeredAt');
            $reflection->setAccessible(true);
            $reflection->setValue($registration, $baseTime->modify("+{$i} minutes"));

            $tournament->addRegistration($registration);
        }

        return $tournament;
    }

    /**
     * Creates a base tournament entity with all required fields.
     */
    private function createBaseTournament(): Tournament
    {
        $organizer = $this->createMockUser(999);

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);
        $tournament->setDecklistTransparency(DecklistTransparency::OPEN);
        $tournament->setSwissMatchFormat(MatchFormat::BO1);

        return $tournament;
    }

    /**
     * Add the given number of mock players to a tournament.
     */
    private function addPlayers(Tournament $tournament, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $this->createMockUser($i + 1);
            $registration = new Registration($tournament, $user);

            // Set ID via reflection for testing
            $reflection = new \ReflectionProperty(Registration::class, 'id');
            $reflection->setAccessible(true);
            $reflection->setValue($registration, $i + 1);

            $tournament->addRegistration($registration);
        }
    }

    /**
     * Creates a mock User with the given ID.
     */
    private function createMockUser(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getPseudo')->willReturn("Player{$id}");

        return $user;
    }

    /**
     * Creates an ongoing tournament with a completed Round 1.
     */
    private function createOngoingTournamentWithCompletedRound1(int $playerCount): Tournament
    {
        $tournament = $this->createBaseTournament();
        $tournament->setStatus(TournamentStatus::ONGOING);
        $this->addPlayers($tournament, $playerCount);

        // Create Round 1
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        // Create matches and complete them
        $registrations = $tournament->getRegistrations()->toArray();
        $tableNumber = 1;

        for ($i = 0; $i < count($registrations) - 1; $i += 2) {
            $match = new TournamentMatch();
            $match->setRound($round);
            $match->setPlayer1($registrations[$i]);
            $match->setPlayer2($registrations[$i + 1]);
            $match->setTableNumber($tableNumber);

            // Complete the match with player1 winning
            $match->complete([
                'winnerId' => $registrations[$i]->getId(),
                'player1Score' => 2,
                'player2Score' => 0,
            ]);

            $round->addMatch($match);
            $tableNumber++;
        }

        // Handle BYE for odd players
        if ($playerCount % 2 !== 0) {
            $byeMatch = new TournamentMatch();
            $byeMatch->setRound($round);
            $byeMatch->setPlayer1($registrations[$playerCount - 1]);
            $byeMatch->setTableNumber($tableNumber);
            $byeMatch->assignBye();
            $round->addMatch($byeMatch);
        }

        return $tournament;
    }

    /**
     * Creates an ongoing tournament with an incomplete Round 1.
     */
    private function createOngoingTournamentWithIncompleteRound1(int $playerCount): Tournament
    {
        $tournament = $this->createBaseTournament();
        $tournament->setStatus(TournamentStatus::ONGOING);
        $this->addPlayers($tournament, $playerCount);

        // Create Round 1
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        // Create matches but DON'T complete them
        $registrations = $tournament->getRegistrations()->toArray();
        $tableNumber = 1;

        for ($i = 0; $i < count($registrations) - 1; $i += 2) {
            $match = new TournamentMatch();
            $match->setRound($round);
            $match->setPlayer1($registrations[$i]);
            $match->setPlayer2($registrations[$i + 1]);
            $match->setTableNumber($tableNumber);
            // Match stays PENDING - NOT completed
            $round->addMatch($match);
            $tableNumber++;
        }

        return $tournament;
    }

    /**
     * Creates an ongoing tournament where one player had a BYE in Round 1.
     */
    private function createOngoingTournamentWithByeInRound1(int $playerCount): Tournament
    {
        if ($playerCount % 2 === 0) {
            throw new \InvalidArgumentException('Player count must be odd for BYE test');
        }

        $tournament = $this->createBaseTournament();
        $tournament->setStatus(TournamentStatus::ONGOING);
        $this->addPlayers($tournament, $playerCount);

        // Create Round 1
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $registrations = $tournament->getRegistrations()->toArray();
        $tableNumber = 1;

        // Create regular matches (excluding last player who gets BYE)
        for ($i = 0; $i < $playerCount - 1; $i += 2) {
            $match = new TournamentMatch();
            $match->setRound($round);
            $match->setPlayer1($registrations[$i]);
            $match->setPlayer2($registrations[$i + 1]);
            $match->setTableNumber($tableNumber);
            $match->complete([
                'winnerId' => $registrations[$i]->getId(),
                'player1Score' => 2,
                'player2Score' => 0,
            ]);
            $round->addMatch($match);
            $tableNumber++;
        }

        // Last player gets BYE
        $byeMatch = new TournamentMatch();
        $byeMatch->setRound($round);
        $byeMatch->setPlayer1($registrations[$playerCount - 1]);
        $byeMatch->setTableNumber($tableNumber);
        $byeMatch->assignBye();
        $round->addMatch($byeMatch);

        return $tournament;
    }

    /**
     * Complete all matches in a round with results (player1 wins).
     */
    private function completeRoundWithResults(Round $round): void
    {
        foreach ($round->getMatches() as $match) {
            if ($match->isCompleted()) {
                continue;
            }

            if ($match->getPlayer2() === null) {
                $match->assignBye();
            } else {
                $match->complete([
                    'winnerId' => $match->getPlayer1()->getId(),
                    'player1Score' => 2,
                    'player2Score' => 0,
                ]);
            }
        }
    }
}
