<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\BracketSize;
use App\Enum\DecklistTransparency;
use App\Enum\MatchFormat;
use App\Enum\MatchStatus;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Exception\InsufficientPlayersException;
use App\Exception\InvalidTournamentStateException;
use App\Service\BracketService;
use App\Service\PairingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BracketServiceTest extends TestCase
{
    private BracketService $service;
    private EntityManagerInterface&MockObject $entityManager;
    private PairingService&MockObject $pairingService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->pairingService = $this->createMock(PairingService::class);

        $this->service = new BracketService(
            $this->entityManager,
            $this->pairingService
        );
    }

    public function testGenerateBracketFirstRoundCreatesCorrectRoundNumber(): void
    {
        $tournament = $this->createOngoingTournamentWithSwissRounds(8, 3);

        $this->pairingService
            ->method('calculateStandings')
            ->willReturn($this->createMockStandings($tournament, 8));

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $round = $this->service->generateBracketFirstRound($tournament, BracketSize::TOP_8);

        // After 3 Swiss rounds, bracket should be round 4
        $this->assertSame(4, $round->getRoundNumber());
    }

    public function testGenerateBracketFirstRoundTop8Creates4Matches(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(8);

        $this->pairingService
            ->method('calculateStandings')
            ->willReturn($this->createMockStandings($tournament, 8));

        $round = $this->service->generateBracketFirstRound($tournament, BracketSize::TOP_8);

        // Top 8 = 4 matches in first round
        $this->assertCount(4, $round->getMatches());
    }

    public function testGenerateBracketFirstRoundTop4Creates2Matches(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        $this->pairingService
            ->method('calculateStandings')
            ->willReturn($this->createMockStandings($tournament, 4));

        $round = $this->service->generateBracketFirstRound($tournament, BracketSize::TOP_4);

        // Top 4 = 2 matches (semis)
        $this->assertCount(2, $round->getMatches());
    }

    public function testGenerateBracketFirstRoundThrowsIfNotOngoing(): void
    {
        $tournament = $this->createTournamentWithStatus(TournamentStatus::PUBLISHED, 8);

        $this->expectException(InvalidTournamentStateException::class);

        $this->service->generateBracketFirstRound($tournament, BracketSize::TOP_8);
    }

    public function testGenerateBracketFirstRoundThrowsIfNotEnoughPlayers(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        $this->pairingService
            ->method('calculateStandings')
            ->willReturn($this->createMockStandings($tournament, 4));

        $this->expectException(InsufficientPlayersException::class);

        // Top 8 requires at least 5 players
        $this->service->generateBracketFirstRound($tournament, BracketSize::TOP_8);
    }

    public function testGenerateBracketFirstRoundHandlesByesForNonPowerOf2(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(6);

        $this->pairingService
            ->method('calculateStandings')
            ->willReturn($this->createMockStandings($tournament, 6));

        $round = $this->service->generateBracketFirstRound($tournament, BracketSize::TOP_8);

        // 6 players in Top 8 = 2 BYEs
        $byeMatches = array_filter(
            $round->getMatches()->toArray(),
            fn ($match) => $match->isByeMatch()
        );
        $this->assertCount(2, $byeMatches);
    }

    public function testGenerateBracketFirstRoundSeedingPattern(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(8);
        $standings = $this->createMockStandings($tournament, 8);

        $this->pairingService
            ->method('calculateStandings')
            ->willReturn($standings);

        $round = $this->service->generateBracketFirstRound($tournament, BracketSize::TOP_8);

        // Standard bracket seeding for 8 players:
        // Match 1: Seed 1 vs Seed 8
        // Match 2: Seed 4 vs Seed 5
        // Match 3: Seed 2 vs Seed 7
        // Match 4: Seed 3 vs Seed 6
        $matches = $round->getMatches()->toArray();

        // Verify first match has seed 1 vs seed 8
        $this->assertSame(1, $matches[0]->getPlayer1()->getId());
        $this->assertSame(8, $matches[0]->getPlayer2()->getId());

        // Verify second match has seed 4 vs seed 5
        $this->assertSame(4, $matches[1]->getPlayer1()->getId());
        $this->assertSame(5, $matches[1]->getPlayer2()->getId());
    }

    public function testGenerateBracketFirstRoundStartsRound(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(8);

        $this->pairingService
            ->method('calculateStandings')
            ->willReturn($this->createMockStandings($tournament, 8));

        $round = $this->service->generateBracketFirstRound($tournament, BracketSize::TOP_8);

        $this->assertTrue($round->isOngoing());
    }

    public function testGetRecommendedBracketSizeForLargeTournament(): void
    {
        $this->assertSame(BracketSize::TOP_32, $this->service->getRecommendedBracketSize(64));
        $this->assertSame(BracketSize::TOP_32, $this->service->getRecommendedBracketSize(32));
    }

    public function testGetRecommendedBracketSizeForMediumTournament(): void
    {
        $this->assertSame(BracketSize::TOP_16, $this->service->getRecommendedBracketSize(24));
        $this->assertSame(BracketSize::TOP_16, $this->service->getRecommendedBracketSize(16));
    }

    public function testGetRecommendedBracketSizeForSmallTournament(): void
    {
        $this->assertSame(BracketSize::TOP_8, $this->service->getRecommendedBracketSize(12));
        $this->assertSame(BracketSize::TOP_8, $this->service->getRecommendedBracketSize(8));
    }

    public function testGetRecommendedBracketSizeForTinyTournament(): void
    {
        $this->assertSame(BracketSize::TOP_4, $this->service->getRecommendedBracketSize(6));
        $this->assertSame(BracketSize::TOP_4, $this->service->getRecommendedBracketSize(4));
    }

    public function testGenerateNextBracketRoundFromCompletedRound(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(8);

        // Create a completed bracket round with 4 matches
        $previousRound = new Round();
        $previousRound->setTournament($tournament);
        $previousRound->setRoundNumber(4);
        $previousRound->start();
        $tournament->addRound($previousRound);

        // Add 4 completed matches with winners
        $registrations = $tournament->getRegistrations()->toArray();
        for ($i = 0; $i < 4; $i++) {
            $match = new TournamentMatch();
            $match->setRound($previousRound);
            $match->setPlayer1($registrations[$i * 2]);
            $match->setPlayer2($registrations[$i * 2 + 1]);
            $match->setTableNumber($i + 1);
            $match->complete([
                'winnerId' => $registrations[$i * 2]->getId(),
                'player1Score' => 2,
                'player2Score' => 0,
            ]);
            $previousRound->addMatch($match);
        }

        $nextRound = $this->service->generateNextBracketRound($tournament, $previousRound);

        // Next round should have 2 matches (semis)
        $this->assertSame(5, $nextRound->getRoundNumber());
        $this->assertCount(2, $nextRound->getMatches());
    }

    public function testIsBracketCompleteReturnsFalseWhenNoRounds(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        $this->assertFalse($this->service->isBracketComplete($tournament));
    }

    public function testIsBracketCompleteReturnsFalseWhenMultipleMatches(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        // Create a round with 2 matches (not the final)
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $registrations = $tournament->getRegistrations()->toArray();
        for ($i = 0; $i < 2; $i++) {
            $match = new TournamentMatch();
            $match->setRound($round);
            $match->setPlayer1($registrations[$i * 2]);
            $match->setPlayer2($registrations[$i * 2 + 1]);
            $match->setTableNumber($i + 1);
            $match->complete([
                'winnerId' => $registrations[$i * 2]->getId(),
                'player1Score' => 2,
                'player2Score' => 0,
            ]);
            $round->addMatch($match);
        }

        $this->assertFalse($this->service->isBracketComplete($tournament));
    }

    public function testIsBracketCompleteReturnsFalseWhenFinalNotCompleted(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(2);

        // Create final round with 1 uncompleted match
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $registrations = $tournament->getRegistrations()->toArray();
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registrations[0]);
        $match->setPlayer2($registrations[1]);
        $match->setTableNumber(1);
        $round->addMatch($match);

        $this->assertFalse($this->service->isBracketComplete($tournament));
    }

    public function testIsBracketCompleteReturnsTrueWhenFinalCompleted(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(2);

        // Create final round with 1 completed match
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $registrations = $tournament->getRegistrations()->toArray();
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registrations[0]);
        $match->setPlayer2($registrations[1]);
        $match->setTableNumber(1);
        $match->complete([
            'winnerId' => $registrations[0]->getId(),
            'player1Score' => 2,
            'player2Score' => 1,
        ]);
        $round->addMatch($match);

        $this->assertTrue($this->service->isBracketComplete($tournament));
    }

    public function testCanAdvanceBracketReturnsFalseWhenMatchesIncomplete(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $registrations = $tournament->getRegistrations()->toArray();
        // One completed, one not
        $match1 = new TournamentMatch();
        $match1->setRound($round);
        $match1->setPlayer1($registrations[0]);
        $match1->setPlayer2($registrations[1]);
        $match1->setTableNumber(1);
        $match1->complete([
            'winnerId' => $registrations[0]->getId(),
            'player1Score' => 2,
            'player2Score' => 0,
        ]);
        $round->addMatch($match1);

        $match2 = new TournamentMatch();
        $match2->setRound($round);
        $match2->setPlayer1($registrations[2]);
        $match2->setPlayer2($registrations[3]);
        $match2->setTableNumber(2);
        $round->addMatch($match2);

        $this->assertFalse($this->service->canAdvanceBracket($round));
    }

    public function testCanAdvanceBracketReturnsTrueWhenAllComplete(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $registrations = $tournament->getRegistrations()->toArray();
        for ($i = 0; $i < 2; $i++) {
            $match = new TournamentMatch();
            $match->setRound($round);
            $match->setPlayer1($registrations[$i * 2]);
            $match->setPlayer2($registrations[$i * 2 + 1]);
            $match->setTableNumber($i + 1);
            $match->complete([
                'winnerId' => $registrations[$i * 2]->getId(),
                'player1Score' => 2,
                'player2Score' => 0,
            ]);
            $round->addMatch($match);
        }

        $this->assertTrue($this->service->canAdvanceBracket($round));
    }

    public function testCanAdvanceBracketReturnsFalseWithOnlyOneWinner(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(2);

        // Final match - can't advance further
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $registrations = $tournament->getRegistrations()->toArray();
        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registrations[0]);
        $match->setPlayer2($registrations[1]);
        $match->setTableNumber(1);
        $match->complete([
            'winnerId' => $registrations[0]->getId(),
            'player1Score' => 2,
            'player2Score' => 0,
        ]);
        $round->addMatch($match);

        // Only 1 winner, can't advance
        $this->assertFalse($this->service->canAdvanceBracket($round));
    }

    public function testGetBracketProgressWithNoRounds(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        $progress = $this->service->getBracketProgress($tournament);

        $this->assertSame(0, $progress['currentRound']);
        $this->assertSame(0, $progress['totalRounds']);
        $this->assertSame(0, $progress['matchesComplete']);
        $this->assertSame(0, $progress['matchesTotal']);
        $this->assertFalse($progress['isComplete']);
    }

    public function testGetBracketProgressWithOngoingRound(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $registrations = $tournament->getRegistrations()->toArray();
        // One completed match
        $match1 = new TournamentMatch();
        $match1->setRound($round);
        $match1->setPlayer1($registrations[0]);
        $match1->setPlayer2($registrations[1]);
        $match1->setTableNumber(1);
        $match1->complete([
            'winnerId' => $registrations[0]->getId(),
            'player1Score' => 2,
            'player2Score' => 0,
        ]);
        $round->addMatch($match1);

        // One incomplete match
        $match2 = new TournamentMatch();
        $match2->setRound($round);
        $match2->setPlayer1($registrations[2]);
        $match2->setPlayer2($registrations[3]);
        $match2->setTableNumber(2);
        $round->addMatch($match2);

        $progress = $this->service->getBracketProgress($tournament);

        $this->assertSame(1, $progress['currentRound']);
        $this->assertSame(1, $progress['totalRounds']);
        $this->assertSame(1, $progress['matchesComplete']);
        $this->assertSame(2, $progress['matchesTotal']);
        $this->assertFalse($progress['isComplete']);
    }

    public function testCalculateFinalStandingsReturnsCorrectOrder(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);
        $registrations = $tournament->getRegistrations()->toArray();

        // Simulate a completed Top 4 bracket (2 rounds)
        // Semi-finals (Round 1): Player 1 beats Player 2, Player 3 beats Player 4
        $round1 = new Round();
        $round1->setTournament($tournament);
        $round1->setRoundNumber(1);
        $round1->start();
        $tournament->addRound($round1);

        $semi1 = new TournamentMatch();
        $semi1->setRound($round1);
        $semi1->setPlayer1($registrations[0]); // Player 1
        $semi1->setPlayer2($registrations[1]); // Player 2
        $semi1->setTableNumber(1);
        $semi1->complete([
            'winnerId' => $registrations[0]->getId(),
            'player1Score' => 2,
            'player2Score' => 0,
        ]);
        $round1->addMatch($semi1);

        $semi2 = new TournamentMatch();
        $semi2->setRound($round1);
        $semi2->setPlayer1($registrations[2]); // Player 3
        $semi2->setPlayer2($registrations[3]); // Player 4
        $semi2->setTableNumber(2);
        $semi2->complete([
            'winnerId' => $registrations[2]->getId(),
            'player1Score' => 2,
            'player2Score' => 1,
        ]);
        $round1->addMatch($semi2);
        $round1->complete();

        // Final (Round 2): Player 1 beats Player 3
        $round2 = new Round();
        $round2->setTournament($tournament);
        $round2->setRoundNumber(2);
        $round2->start();
        $tournament->addRound($round2);

        $final = new TournamentMatch();
        $final->setRound($round2);
        $final->setPlayer1($registrations[0]); // Player 1
        $final->setPlayer2($registrations[2]); // Player 3
        $final->setTableNumber(1);
        $final->complete([
            'winnerId' => $registrations[0]->getId(),
            'player1Score' => 2,
            'player2Score' => 1,
        ]);
        $round2->addMatch($final);
        $round2->complete();

        $standings = $this->service->calculateFinalStandings($tournament);

        // Expected: 1st = Player 1, 2nd = Player 3, 3rd-4th = Players 2 and 4
        $this->assertCount(4, $standings);

        // 1st place - Player 1 (winner)
        $this->assertSame(1, $standings[0]['rank']);
        $this->assertSame($registrations[0], $standings[0]['registration']);

        // 2nd place - Player 3 (finalist loser)
        $this->assertSame(2, $standings[1]['rank']);
        $this->assertSame($registrations[2], $standings[1]['registration']);

        // 3rd-4th place - semi-final losers (Players 2 and 4)
        $this->assertSame(3, $standings[2]['rank']);
        $this->assertSame(3, $standings[3]['rank']);
    }

    public function testCompleteTournamentSetsCorrectStatus(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(2);
        $registrations = $tournament->getRegistrations()->toArray();

        // Create completed final
        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);
        $round->start();
        $tournament->addRound($round);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registrations[0]);
        $match->setPlayer2($registrations[1]);
        $match->setTableNumber(1);
        $match->complete([
            'winnerId' => $registrations[0]->getId(),
            'player1Score' => 2,
            'player2Score' => 0,
        ]);
        $round->addMatch($match);
        $round->complete();

        $this->entityManager->expects($this->once())->method('flush');

        $standings = $this->service->completeTournament($tournament);

        $this->assertSame(TournamentStatus::COMPLETED, $tournament->getStatus());
        $this->assertNotNull($tournament->getCompletedAt());
        $this->assertCount(2, $standings);
    }

    public function testCompleteTournamentThrowsWhenNotComplete(): void
    {
        $tournament = $this->createOngoingTournamentWithPlayers(4);

        // No rounds - bracket not complete
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Le bracket n\'est pas encore termine.');

        $this->service->completeTournament($tournament);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function createTournamentWithStatus(TournamentStatus $status, int $playerCount): Tournament
    {
        $tournament = $this->createBaseTournament();
        $tournament->setStatus($status);
        $this->addPlayers($tournament, $playerCount);

        return $tournament;
    }

    private function createOngoingTournamentWithPlayers(int $playerCount): Tournament
    {
        return $this->createTournamentWithStatus(TournamentStatus::ONGOING, $playerCount);
    }

    private function createOngoingTournamentWithSwissRounds(int $playerCount, int $roundCount): Tournament
    {
        $tournament = $this->createOngoingTournamentWithPlayers($playerCount);

        for ($i = 1; $i <= $roundCount; $i++) {
            $round = new Round();
            $round->setTournament($tournament);
            $round->setRoundNumber($i);
            $round->start();
            $round->complete();
            $tournament->addRound($round);
        }

        return $tournament;
    }

    private function createBaseTournament(): Tournament
    {
        $organizer = $this->createMockUser(999);

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $tournament->setStructure(TournamentStructure::MIXED);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);
        $tournament->setDecklistTransparency(DecklistTransparency::OPEN);
        $tournament->setSwissMatchFormat(MatchFormat::BO1);

        return $tournament;
    }

    private function addPlayers(Tournament $tournament, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $this->createMockUser($i + 1);
            $registration = new Registration($tournament, $user);

            $reflection = new \ReflectionProperty(Registration::class, 'id');
            $reflection->setAccessible(true);
            $reflection->setValue($registration, $i + 1);

            $tournament->addRegistration($registration);
        }
    }

    private function createMockUser(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getPseudo')->willReturn("Player{$id}");

        return $user;
    }

    /**
     * @return array<int, \App\Service\Pairing\PlayerStandings>
     */
    private function createMockStandings(Tournament $tournament, int $count): array
    {
        $standings = [];
        $registrations = $tournament->getRegistrations()->toArray();

        for ($i = 0; $i < min($count, count($registrations)); $i++) {
            $standing = new \App\Service\Pairing\PlayerStandings($registrations[$i]);
            // Give descending points so standings are in order
            for ($j = 0; $j < $count - $i; $j++) {
                $standing->addWin();
            }
            $standings[$registrations[$i]->getId()] = $standing;
        }

        return $standings;
    }
}
