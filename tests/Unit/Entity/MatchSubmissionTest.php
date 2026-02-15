<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\MatchSubmission;
use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use PHPUnit\Framework\TestCase;

final class MatchSubmissionTest extends TestCase
{
    public function testConstructorSetsSubmittedAt(): void
    {
        $submission = new MatchSubmission();

        $this->assertInstanceOf(\DateTimeImmutable::class, $submission->getSubmittedAt());
    }

    public function testSetAndGetMatch(): void
    {
        $submission = new MatchSubmission();
        $match = $this->createMatch();

        $submission->setMatch($match);

        $this->assertSame($match, $submission->getMatch());
    }

    public function testSetAndGetSubmittedBy(): void
    {
        $submission = new MatchSubmission();
        $user = $this->createMockUser(1);

        $submission->setSubmittedBy($user);

        $this->assertSame($user, $submission->getSubmittedBy());
    }

    public function testSetAndGetClaimedWinnerId(): void
    {
        $submission = new MatchSubmission();

        $submission->setClaimedWinnerId(42);

        $this->assertSame(42, $submission->getClaimedWinnerId());
    }

    public function testSetAndGetScores(): void
    {
        $submission = new MatchSubmission();

        $submission->setPlayer1Score(2);
        $submission->setPlayer2Score(1);

        $this->assertSame(2, $submission->getPlayer1Score());
        $this->assertSame(1, $submission->getPlayer2Score());
    }

    public function testSetAndGetGameDetails(): void
    {
        $submission = new MatchSubmission();
        $gameDetails = [
            ['winner' => 'player1'],
            ['winner' => 'player2'],
            ['winner' => 'player1'],
        ];

        $submission->setGameDetails($gameDetails);

        $this->assertSame($gameDetails, $submission->getGameDetails());
    }

    public function testGameDetailsIsNullByDefault(): void
    {
        $submission = new MatchSubmission();

        $this->assertNull($submission->getGameDetails());
    }

    public function testAgreesWithMatchingSubmission(): void
    {
        $submission1 = new MatchSubmission();
        $submission1->setClaimedWinnerId(1);
        $submission1->setPlayer1Score(2);
        $submission1->setPlayer2Score(0);

        $submission2 = new MatchSubmission();
        $submission2->setClaimedWinnerId(1);
        $submission2->setPlayer1Score(2);
        $submission2->setPlayer2Score(0);

        $this->assertTrue($submission1->agreesWith($submission2));
        $this->assertTrue($submission2->agreesWith($submission1));
    }

    public function testAgreesWithDifferentWinner(): void
    {
        $submission1 = new MatchSubmission();
        $submission1->setClaimedWinnerId(1);
        $submission1->setPlayer1Score(2);
        $submission1->setPlayer2Score(0);

        $submission2 = new MatchSubmission();
        $submission2->setClaimedWinnerId(2);
        $submission2->setPlayer1Score(0);
        $submission2->setPlayer2Score(2);

        $this->assertFalse($submission1->agreesWith($submission2));
    }

    public function testAgreesWithDifferentScore(): void
    {
        $submission1 = new MatchSubmission();
        $submission1->setClaimedWinnerId(1);
        $submission1->setPlayer1Score(2);
        $submission1->setPlayer2Score(0);

        $submission2 = new MatchSubmission();
        $submission2->setClaimedWinnerId(1);
        $submission2->setPlayer1Score(2);
        $submission2->setPlayer2Score(1);

        $this->assertFalse($submission1->agreesWith($submission2));
    }

    public function testToResultArray(): void
    {
        $submission = new MatchSubmission();
        $submission->setClaimedWinnerId(42);
        $submission->setPlayer1Score(2);
        $submission->setPlayer2Score(1);
        $submission->setGameDetails([['winner' => 'player1']]);

        $result = $submission->toResultArray();

        $this->assertSame(42, $result['winnerId']);
        $this->assertSame(2, $result['player1Score']);
        $this->assertSame(1, $result['player2Score']);
        $this->assertSame([['winner' => 'player1']], $result['gameDetails']);
    }

    public function testClaimsSubmitterWonReturnsTrue(): void
    {
        $user = $this->createMockUser(1);
        $tournament = $this->createTournament($user);
        $registration = new Registration($tournament, $user);

        $reflection = new \ReflectionProperty(Registration::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($registration, 10);

        $match = $this->createMatch();
        $match->setPlayer1($registration);

        $submission = new MatchSubmission();
        $submission->setMatch($match);
        $submission->setSubmittedBy($user);
        $submission->setClaimedWinnerId(10);

        $this->assertTrue($submission->claimsSubmitterWon());
    }

    public function testClaimsSubmitterWonReturnsFalse(): void
    {
        $user = $this->createMockUser(1);
        $tournament = $this->createTournament($user);
        $registration = new Registration($tournament, $user);

        $reflection = new \ReflectionProperty(Registration::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($registration, 10);

        $match = $this->createMatch();
        $match->setPlayer1($registration);

        $submission = new MatchSubmission();
        $submission->setMatch($match);
        $submission->setSubmittedBy($user);
        $submission->setClaimedWinnerId(20); // Different winner

        $this->assertFalse($submission->claimsSubmitterWon());
    }

    public function testFindSubmitterRegistrationForPlayer1(): void
    {
        $user = $this->createMockUser(1);
        $tournament = $this->createTournament($user);
        $registration = new Registration($tournament, $user);

        $match = $this->createMatch();
        $match->setPlayer1($registration);

        $submission = new MatchSubmission();
        $submission->setMatch($match);
        $submission->setSubmittedBy($user);

        $this->assertSame($registration, $submission->findSubmitterRegistration());
    }

    public function testFindSubmitterRegistrationForPlayer2(): void
    {
        $user1 = $this->createMockUser(1);
        $user2 = $this->createMockUser(2);
        $tournament = $this->createTournament($user1);
        $registration1 = new Registration($tournament, $user1);
        $registration2 = new Registration($tournament, $user2);

        $match = $this->createMatch();
        $match->setPlayer1($registration1);
        $match->setPlayer2($registration2);

        $submission = new MatchSubmission();
        $submission->setMatch($match);
        $submission->setSubmittedBy($user2);

        $this->assertSame($registration2, $submission->findSubmitterRegistration());
    }

    public function testFindSubmitterRegistrationReturnsNullForNonParticipant(): void
    {
        $user1 = $this->createMockUser(1);
        $user2 = $this->createMockUser(2);
        $user3 = $this->createMockUser(3);
        $tournament = $this->createTournament($user1);
        $registration1 = new Registration($tournament, $user1);
        $registration2 = new Registration($tournament, $user2);

        $match = $this->createMatch();
        $match->setPlayer1($registration1);
        $match->setPlayer2($registration2);

        $submission = new MatchSubmission();
        $submission->setMatch($match);
        $submission->setSubmittedBy($user3);

        $this->assertNull($submission->findSubmitterRegistration());
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function createMockUser(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);

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

    private function createMatch(): TournamentMatch
    {
        $match = new TournamentMatch();
        $match->setTableNumber(1);

        $round = new Round();
        $match->setRound($round);

        return $match;
    }
}
