<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\MatchSubmission;
use App\Entity\Registration;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use App\Exception\InvalidSubmissionException;
use App\Service\ResultSubmissionService;
use App\Service\StandingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResultSubmissionServiceTest extends TestCase
{
    private ResultSubmissionService $service;
    private EntityManagerInterface&MockObject $entityManager;
    private StandingsService&MockObject $standingsService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->standingsService = $this->createMock(StandingsService::class);

        $this->service = new ResultSubmissionService(
            $this->entityManager,
            $this->standingsService
        );
    }

    public function testSubmitResultCreatesSubmission(): void
    {
        $match = $this->createMatch();
        $user = $match->getPlayer1()->getPlayer();

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $submission = $this->service->submitResult(
            $match,
            $user,
            $match->getPlayer1()->getId(),
            2,
            0
        );

        $this->assertInstanceOf(MatchSubmission::class, $submission);
        $this->assertSame($match, $submission->getMatch());
        $this->assertSame($user, $submission->getSubmittedBy());
        $this->assertSame($match->getPlayer1()->getId(), $submission->getClaimedWinnerId());
        $this->assertSame(2, $submission->getPlayer1Score());
        $this->assertSame(0, $submission->getPlayer2Score());
    }

    public function testSubmitResultChangesStatusToOngoing(): void
    {
        $match = $this->createMatch();
        $user = $match->getPlayer1()->getPlayer();

        $this->service->submitResult(
            $match,
            $user,
            $match->getPlayer1()->getId(),
            2,
            0
        );

        $this->assertSame(MatchStatus::ONGOING, $match->getStatus());
    }

    public function testSubmitResultThrowsIfMatchCompleted(): void
    {
        $match = $this->createMatch();
        $match->setStatus(MatchStatus::COMPLETED);
        $user = $match->getPlayer1()->getPlayer();

        $this->expectException(InvalidSubmissionException::class);
        $this->expectExceptionMessage('deja termine');

        $this->service->submitResult(
            $match,
            $user,
            $match->getPlayer1()->getId(),
            2,
            0
        );
    }

    public function testSubmitResultThrowsIfByeMatch(): void
    {
        $match = $this->createMatch();
        $match->setIsBye(true);
        $match->setPlayer2(null);
        $user = $match->getPlayer1()->getPlayer();

        $this->expectException(InvalidSubmissionException::class);
        $this->expectExceptionMessage('BYE');

        $this->service->submitResult(
            $match,
            $user,
            $match->getPlayer1()->getId(),
            2,
            0
        );
    }

    public function testSubmitResultThrowsIfNotParticipant(): void
    {
        $match = $this->createMatch();
        $nonParticipant = $this->createMockUser(999);

        $this->expectException(InvalidSubmissionException::class);
        $this->expectExceptionMessage('participant');

        $this->service->submitResult(
            $match,
            $nonParticipant,
            $match->getPlayer1()->getId(),
            2,
            0
        );
    }

    public function testSubmitResultThrowsIfAlreadySubmitted(): void
    {
        $match = $this->createMatch();
        $user = $match->getPlayer1()->getPlayer();

        $previous = new MatchSubmission();
        $previous->setSubmittedBy($user);
        $match->addSubmission($previous);

        $this->expectException(InvalidSubmissionException::class);
        $this->expectExceptionMessage('deja soumis');

        $this->service->submitResult(
            $match,
            $user,
            $match->getPlayer1()->getId(),
            2,
            0
        );
    }

    public function testAutoValidationWhenBothPlayersAgree(): void
    {
        $match = $this->createMatch();
        $player1User = $match->getPlayer1()->getPlayer();
        $player2User = $match->getPlayer2()->getPlayer();

        // First submission
        $this->service->submitResult(
            $match,
            $player1User,
            $match->getPlayer1()->getId(),
            2,
            1
        );

        $this->assertFalse($match->isCompleted());

        // Second submission (matching)
        $this->service->submitResult(
            $match,
            $player2User,
            $match->getPlayer1()->getId(),
            2,
            1
        );

        // Match should now be completed
        $this->assertTrue($match->isCompleted());
        $this->assertSame($match->getPlayer1()->getId(), $match->getResult()['winnerId']);
    }

    public function testDisputeCreatedWhenPlayersDisagree(): void
    {
        $match = $this->createMatch();
        $player1User = $match->getPlayer1()->getPlayer();
        $player2User = $match->getPlayer2()->getPlayer();

        // First submission - player1 claims win
        $this->service->submitResult(
            $match,
            $player1User,
            $match->getPlayer1()->getId(),
            2,
            1
        );

        // Second submission - player2 also claims win (conflict!)
        $this->service->submitResult(
            $match,
            $player2User,
            $match->getPlayer2()->getId(),
            1,
            2
        );

        // Match should be disputed
        $this->assertTrue($match->isDisputed());
        $this->assertFalse($match->isCompleted());
    }

    public function testCanUserSubmitReturnsTrue(): void
    {
        $match = $this->createMatch();
        $user = $match->getPlayer1()->getPlayer();

        $this->assertTrue($this->service->canUserSubmit($match, $user));
    }

    public function testCanUserSubmitReturnsFalseForCompletedMatch(): void
    {
        $match = $this->createMatch();
        $match->setStatus(MatchStatus::COMPLETED);
        $user = $match->getPlayer1()->getPlayer();

        $this->assertFalse($this->service->canUserSubmit($match, $user));
    }

    public function testCanUserSubmitReturnsFalseForNonParticipant(): void
    {
        $match = $this->createMatch();
        $nonParticipant = $this->createMockUser(999);

        $this->assertFalse($this->service->canUserSubmit($match, $nonParticipant));
    }

    public function testResolveDisputeSetsResult(): void
    {
        $match = $this->createMatch();
        $match->dispute();
        $resolver = $this->createMockUser(100);

        $this->service->resolveDispute(
            $match,
            $resolver,
            $match->getPlayer1()->getId(),
            2,
            0
        );

        $this->assertTrue($match->isCompleted());
        $this->assertSame($match->getPlayer1()->getId(), $match->getResult()['winnerId']);
    }

    public function testResolveDisputeThrowsIfNotDisputed(): void
    {
        $match = $this->createMatch();
        $resolver = $this->createMockUser(100);

        $this->expectException(\LogicException::class);

        $this->service->resolveDispute(
            $match,
            $resolver,
            $match->getPlayer1()->getId(),
            2,
            0
        );
    }

    public function testForceResultOnAnyMatch(): void
    {
        $match = $this->createMatch();
        $match->setStatus(MatchStatus::ONGOING);
        $resolver = $this->createMockUser(100);

        $this->service->forceResult(
            $match,
            $resolver,
            $match->getPlayer2()->getId(),
            0,
            2
        );

        $this->assertTrue($match->isCompleted());
        $this->assertSame($match->getPlayer2()->getId(), $match->getResult()['winnerId']);
    }

    public function testForceResultThrowsOnByeMatch(): void
    {
        $match = $this->createMatch();
        $match->setIsBye(true);
        $match->setPlayer2(null);
        $resolver = $this->createMockUser(100);

        $this->expectException(\LogicException::class);

        $this->service->forceResult(
            $match,
            $resolver,
            $match->getPlayer1()->getId(),
            2,
            0
        );
    }

    public function testDisputeHistoryIsRecorded(): void
    {
        $match = $this->createMatch();
        $player1User = $match->getPlayer1()->getPlayer();
        $player2User = $match->getPlayer2()->getPlayer();

        // Create a dispute
        $this->service->submitResult(
            $match,
            $player1User,
            $match->getPlayer1()->getId(),
            2,
            0
        );
        $this->service->submitResult(
            $match,
            $player2User,
            $match->getPlayer2()->getId(),
            0,
            2
        );

        $history = $match->getDisputeHistory();
        $this->assertNotNull($history);
        $this->assertGreaterThanOrEqual(3, count($history)); // 2 submissions + 1 dispute created
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    public function testTeammateCannotSubmitAfterPartnerAlreadyDid(): void
    {
        $match = $this->createTeamMatch();
        $captain = $match->getPlayer1()->getPlayer();
        $teammate = $match->getPlayer1()->getPartner();

        $this->service->submitResult($match, $captain, $match->getPlayer1()->getId(), 2, 0);

        $this->expectException(InvalidSubmissionException::class);
        $this->service->submitResult($match, $teammate, $match->getPlayer1()->getId(), 2, 0);
    }

    public function testTeamMatchValidatedWhenEachTeamSubmitsOnce(): void
    {
        $match = $this->createTeamMatch();

        // The partner (not the captain) of each team submits
        $this->service->submitResult($match, $match->getPlayer1()->getPartner(), $match->getPlayer2()->getId(), 0, 2);
        $this->assertFalse($match->isCompleted());
        $this->service->submitResult($match, $match->getPlayer2()->getPartner(), $match->getPlayer2()->getId(), 0, 2);

        $this->assertTrue($match->isCompleted());
        $this->assertSame($match->getPlayer2()->getId(), $match->getResult()['winnerId']);
    }

    public function testTeammateSeesTheTeamSubmission(): void
    {
        $match = $this->createTeamMatch();
        $this->service->submitResult($match, $match->getPlayer1()->getPlayer(), $match->getPlayer1()->getId(), 2, 1);

        $submission = $this->service->getPlayerSubmission($match, $match->getPlayer1()->getPartner());

        $this->assertNotNull($submission);
        $this->assertNull($this->service->getPlayerSubmission($match, $match->getPlayer2()->getPartner()));
    }

    public function testSingleSubmissionValidatesAgainstAllGuestTeam(): void
    {
        $match = $this->createTeamMatch(opponentGuests: [true, true]);

        $this->service->submitResult($match, $match->getPlayer1()->getPlayer(), $match->getPlayer1()->getId(), 2, 0);

        $this->assertTrue($match->isCompleted());
    }

    public function testSingleSubmissionWaitsWhenOpponentTeamHasARealAccount(): void
    {
        $match = $this->createTeamMatch(opponentGuests: [true, false]);

        $this->service->submitResult($match, $match->getPlayer1()->getPlayer(), $match->getPlayer1()->getId(), 2, 0);

        $this->assertFalse($match->isCompleted());
    }

    /**
     * Team match: (users 1 & 3) vs (users 2 & 4).
     *
     * @param array{bool, bool} $opponentGuests whether users 2 and 4 are guests
     */
    private function createTeamMatch(array $opponentGuests = [false, false]): TournamentMatch
    {
        $match = $this->createMatch();
        $match->getRound()->getTournament()->setTeamSize(2);
        $match->getPlayer1()->setPartner($this->createMockUser(3));
        $match->getPlayer2()->setPartner($this->createMockUser(4, $opponentGuests[1]));

        if ($opponentGuests[0]) {
            $guestCaptain = $this->createMockUser(2, true);
            $reflection = new \ReflectionProperty(Registration::class, 'player');
            $reflection->setValue($match->getPlayer2(), $guestCaptain);
        }

        return $match;
    }

    private function createMockUser(int $id, bool $guest = false): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('isGuest')->willReturn($guest);
        $user->method('getPseudo')->willReturn("Player{$id}");

        return $user;
    }

    private function createMatch(): TournamentMatch
    {
        $organizer = $this->createMockUser(999);

        $tournament = new Tournament();
        $tournament->setName('Test Tournament');
        $tournament->setOrganizer($organizer);
        $tournament->setDate(new \DateTimeImmutable());
        $tournament->setFormat(TournamentFormat::CONSTRUCTED_STANDARD);
        $tournament->setStructure(TournamentStructure::SWISS_ONLY);
        $tournament->setVisibility(TournamentVisibility::PUBLIC);

        $user1 = $this->createMockUser(1);
        $user2 = $this->createMockUser(2);

        $registration1 = new Registration($tournament, $user1);
        $reflection1 = new \ReflectionProperty(Registration::class, 'id');
        $reflection1->setAccessible(true);
        $reflection1->setValue($registration1, 1);

        $registration2 = new Registration($tournament, $user2);
        $reflection2 = new \ReflectionProperty(Registration::class, 'id');
        $reflection2->setAccessible(true);
        $reflection2->setValue($registration2, 2);

        $round = new Round();
        $round->setTournament($tournament);
        $round->setRoundNumber(1);

        $match = new TournamentMatch();
        $match->setRound($round);
        $match->setPlayer1($registration1);
        $match->setPlayer2($registration2);
        $match->setTableNumber(1);

        return $match;
    }
}
