<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentStatus;
use App\Enum\TournamentVisibility;
use App\Repository\RegistrationRepository;
use App\Security\Voter\TournamentVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @covers \App\Security\Voter\TournamentVoter
 */
final class TournamentVoterTest extends TestCase
{
    private TournamentVoter $voter;
    private RegistrationRepository&MockObject $registrationRepository;

    protected function setUp(): void
    {
        $this->registrationRepository = $this->createMock(RegistrationRepository::class);
        $this->voter = new TournamentVoter($this->registrationRepository);
    }

    // ====== EDIT Permission Tests ======

    public function testEditGrantedForOrganizerWithDraftStatus(): void
    {
        $user = $this->createUser(1);
        $tournament = $this->createTournament($user, TournamentStatus::DRAFT);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditDeniedForNonOrganizer(): void
    {
        $organizer = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $tournament = $this->createTournament($organizer, TournamentStatus::DRAFT);
        $token = $this->createToken($otherUser);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForPublishedStatus(): void
    {
        $user = $this->createUser(1);
        $tournament = $this->createTournament($user, TournamentStatus::PUBLISHED);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForOngoingStatus(): void
    {
        $user = $this->createUser(1);
        $tournament = $this->createTournament($user, TournamentStatus::ONGOING);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForCompletedStatus(): void
    {
        $user = $this->createUser(1);
        $tournament = $this->createTournament($user, TournamentStatus::COMPLETED);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForCancelledStatus(): void
    {
        $user = $this->createUser(1);
        $tournament = $this->createTournament($user, TournamentStatus::CANCELLED);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForAnonymousUser(): void
    {
        $organizer = $this->createUser(1);
        $tournament = $this->createTournament($organizer, TournamentStatus::DRAFT);
        $token = $this->createAnonymousToken();

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ====== DELETE Permission Tests ======

    public function testDeleteGrantedForOrganizerWithDraftStatus(): void
    {
        $user = $this->createUser(1);
        $tournament = $this->createTournament($user, TournamentStatus::DRAFT);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeleteDeniedForNonOrganizer(): void
    {
        $organizer = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $tournament = $this->createTournament($organizer, TournamentStatus::DRAFT);
        $token = $this->createToken($otherUser);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeleteDeniedForPublishedStatus(): void
    {
        $user = $this->createUser(1);
        $tournament = $this->createTournament($user, TournamentStatus::PUBLISHED);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ====== MANAGE Permission Tests ======

    public function testManageGrantedForOrganizerAnyStatus(): void
    {
        $user = $this->createUser(1);
        $token = $this->createToken($user);

        foreach (TournamentStatus::cases() as $status) {
            $tournament = $this->createTournament($user, $status);
            $result = $this->voter->vote($token, $tournament, [TournamentVoter::MANAGE]);

            $this->assertSame(
                VoterInterface::ACCESS_GRANTED,
                $result,
                sprintf('MANAGE should be granted for organizer with status %s', $status->value)
            );
        }
    }

    public function testManageDeniedForNonOrganizer(): void
    {
        $organizer = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $tournament = $this->createTournament($organizer, TournamentStatus::DRAFT);
        $token = $this->createToken($otherUser);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::MANAGE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testManageDeniedForAnonymousUser(): void
    {
        $organizer = $this->createUser(1);
        $tournament = $this->createTournament($organizer, TournamentStatus::DRAFT);
        $token = $this->createAnonymousToken();

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::MANAGE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ====== VIEW Permission Tests ======

    public function testViewGrantedForPublicPublishedTournament(): void
    {
        $organizer = $this->createUser(1);
        $tournament = $this->createTournament(
            $organizer,
            TournamentStatus::PUBLISHED,
            TournamentVisibility::PUBLIC
        );
        $token = $this->createAnonymousToken();

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewDeniedForPublicDraftTournamentByAnonymous(): void
    {
        $organizer = $this->createUser(1);
        $tournament = $this->createTournament(
            $organizer,
            TournamentStatus::DRAFT,
            TournamentVisibility::PUBLIC
        );
        $token = $this->createAnonymousToken();

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testViewDeniedForPrivateTournamentByAnonymous(): void
    {
        $organizer = $this->createUser(1);
        $tournament = $this->createTournament(
            $organizer,
            TournamentStatus::PUBLISHED,
            TournamentVisibility::PRIVATE
        );
        $token = $this->createAnonymousToken();

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testViewGrantedForOrganizerOnPrivateTournament(): void
    {
        $organizer = $this->createUser(1);
        $tournament = $this->createTournament(
            $organizer,
            TournamentStatus::DRAFT,
            TournamentVisibility::PRIVATE
        );
        $token = $this->createToken($organizer);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedForAuthenticatedUserOnPublicTournament(): void
    {
        $organizer = $this->createUser(1);
        $viewer = $this->createUser(2);
        $tournament = $this->createTournament(
            $organizer,
            TournamentStatus::PUBLISHED,
            TournamentVisibility::PUBLIC
        );
        $token = $this->createToken($viewer);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedForAdminOnPrivateTournament(): void
    {
        $organizer = $this->createUser(1);
        $admin = $this->createUser(2, ['ROLE_ADMIN']);
        $tournament = $this->createTournament(
            $organizer,
            TournamentStatus::PUBLISHED,
            TournamentVisibility::PRIVATE
        );
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewDashboardGrantedForAdminOnPrivateTournament(): void
    {
        $organizer = $this->createUser(1);
        $admin = $this->createUser(2, ['ROLE_ADMIN']);
        $tournament = $this->createTournament(
            $organizer,
            TournamentStatus::ONGOING,
            TournamentVisibility::PRIVATE
        );
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::VIEW_DASHBOARD]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewDeniedForNonAdminNonParticipantOnPrivateTournament(): void
    {
        $organizer = $this->createUser(1);
        $regularUser = $this->createUser(2);
        $tournament = $this->createTournament(
            $organizer,
            TournamentStatus::PUBLISHED,
            TournamentVisibility::PRIVATE
        );
        $token = $this->createToken($regularUser);

        $result = $this->voter->vote($token, $tournament, [TournamentVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ====== Abstain Tests ======

    public function testAbstainForUnsupportedAttribute(): void
    {
        $user = $this->createUser(1);
        $tournament = $this->createTournament($user, TournamentStatus::DRAFT);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $tournament, ['UNSUPPORTED_ATTRIBUTE']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainForNonTournamentSubject(): void
    {
        $user = $this->createUser(1);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, new \stdClass(), [TournamentVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // ====== Helper Methods ======

    private function createUser(int $id, array $roles = ['ROLE_USER']): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getRoles')->willReturn($roles);

        return $user;
    }

    private function createTournament(
        User $organizer,
        TournamentStatus $status,
        TournamentVisibility $visibility = TournamentVisibility::PUBLIC,
        bool $hasRounds = true
    ): Tournament {
        $tournament = $this->createMock(Tournament::class);
        $tournament->method('getOrganizer')->willReturn($organizer);
        $tournament->method('getStatus')->willReturn($status);
        $tournament->method('getVisibility')->willReturn($visibility);
        $tournament->method('hasRounds')->willReturn($hasRounds);

        return $tournament;
    }

    private function createToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createAnonymousToken(): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        return $token;
    }
}
