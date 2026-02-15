<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentStatus;
use App\Enum\TournamentVisibility;
use App\Repository\RegistrationRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter for Tournament entity permissions.
 *
 * Permission Matrix:
 * - EDIT: Organizer only, DRAFT or PUBLISHED (not started) status
 * - DELETE: Organizer only, DRAFT or PUBLISHED (not started) status
 * - MANAGE: Organizer only, any status (for dashboard access, publish, start)
 * - MANAGE_REGISTRATIONS: Organizer or admin
 * - VIEW: Public tournaments visible to all, private to organizer or admin
 * - VIEW_DASHBOARD: Organizer, admin, registered players, or anyone for public ONGOING/COMPLETED tournaments
 *
 * @extends Voter<string, Tournament>
 */
final class TournamentVoter extends Voter
{
    public const EDIT = 'TOURNAMENT_EDIT';
    public const DELETE = 'TOURNAMENT_DELETE';
    public const MANAGE = 'TOURNAMENT_MANAGE';
    public const MANAGE_REGISTRATIONS = 'TOURNAMENT_MANAGE_REGISTRATIONS';
    public const VIEW = 'TOURNAMENT_VIEW';
    public const VIEW_DASHBOARD = 'TOURNAMENT_VIEW_DASHBOARD';

    public function __construct(
        private readonly RegistrationRepository $registrationRepository
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::MANAGE, self::MANAGE_REGISTRATIONS, self::VIEW, self::VIEW_DASHBOARD], true)
            && $subject instanceof Tournament;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        /** @var Tournament $tournament */
        $tournament = $subject;

        // Anonymous users can view public tournaments and public dashboards
        if (!$user instanceof User) {
            if ($attribute === self::VIEW) {
                return $this->isPubliclyViewable($tournament);
            }
            if ($attribute === self::VIEW_DASHBOARD) {
                return $this->canViewDashboardPublicly($tournament);
            }
            return false;
        }

        return match ($attribute) {
            self::EDIT => $this->canEdit($tournament, $user),
            self::DELETE => $this->canDelete($tournament, $user),
            self::MANAGE => $this->canManage($tournament, $user),
            self::MANAGE_REGISTRATIONS => $this->canManageRegistrations($tournament, $user),
            self::VIEW => $this->canView($tournament, $user),
            self::VIEW_DASHBOARD => $this->canViewDashboard($tournament, $user),
            default => false,
        };
    }

    /**
     * Can edit: Must be organizer AND tournament must not have started.
     * Editing is allowed in DRAFT and PUBLISHED status until the first round is generated.
     */
    private function canEdit(Tournament $tournament, User $user): bool
    {
        if (!$this->isOrganizer($tournament, $user)) {
            return false;
        }

        // Can edit if DRAFT
        if ($tournament->getStatus() === TournamentStatus::DRAFT) {
            return true;
        }

        // Can edit if PUBLISHED but no rounds started yet
        if ($tournament->getStatus() === TournamentStatus::PUBLISHED && !$tournament->hasRounds()) {
            return true;
        }

        return false;
    }

    /**
     * Can delete: Must be organizer AND tournament not started (DRAFT or PUBLISHED without rounds).
     * Registrations are removed by the service layer when deleting a published tournament.
     */
    private function canDelete(Tournament $tournament, User $user): bool
    {
        return $this->canEdit($tournament, $user);
    }

    /**
     * Can manage: Must be organizer (any status).
     * Allows dashboard access, publish, start, dispute resolution, etc.
     */
    private function canManage(Tournament $tournament, User $user): bool
    {
        return $this->isOrganizer($tournament, $user);
    }

    /**
     * Can manage registrations: Must be organizer OR admin.
     * Allows viewing player list, removing players, closing registrations.
     */
    private function canManageRegistrations(Tournament $tournament, User $user): bool
    {
        // Organizer can always manage registrations
        if ($this->isOrganizer($tournament, $user)) {
            return true;
        }

        // Admin can manage any tournament's registrations
        return $this->isAdmin($user);
    }

    /**
     * Can view: Public tournaments viewable by all,
     * private tournaments viewable by organizer or admin.
     */
    private function canView(Tournament $tournament, User $user): bool
    {
        if ($this->isPubliclyViewable($tournament)) {
            return true;
        }

        // Organizer can always view their tournament
        if ($this->isOrganizer($tournament, $user)) {
            return true;
        }

        // Admin can view any tournament (including private)
        return $this->isAdmin($user);
    }

    private function isOrganizer(Tournament $tournament, User $user): bool
    {
        return $tournament->getOrganizer()->getId() === $user->getId();
    }

    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    private function isPubliclyViewable(Tournament $tournament): bool
    {
        // Only show published/ongoing/completed public tournaments to anonymous users
        if ($tournament->getVisibility() !== TournamentVisibility::PUBLIC) {
            return false;
        }

        // Don't show drafts publicly
        return $tournament->getStatus() !== TournamentStatus::DRAFT;
    }

    /**
     * Can view dashboard: Organizer, admin, registered players, or anyone for public ongoing/completed tournaments.
     */
    private function canViewDashboard(Tournament $tournament, User $user): bool
    {
        // Organizer can always view dashboard
        if ($this->isOrganizer($tournament, $user)) {
            return true;
        }

        // Admin can view any tournament's dashboard
        if ($this->isAdmin($user)) {
            return true;
        }

        // Public tournaments that are ONGOING or COMPLETED are viewable by anyone
        if ($this->canViewDashboardPublicly($tournament)) {
            return true;
        }

        // Registered players can view dashboard of their tournament (even if private)
        if (in_array($tournament->getStatus(), [TournamentStatus::ONGOING, TournamentStatus::COMPLETED], true)) {
            return $this->registrationRepository->isPlayerRegistered($tournament, $user);
        }

        return false;
    }

    /**
     * Can view dashboard publicly (anonymous access).
     * Only PUBLIC tournaments that are ONGOING or COMPLETED.
     */
    private function canViewDashboardPublicly(Tournament $tournament): bool
    {
        if ($tournament->getVisibility() !== TournamentVisibility::PUBLIC) {
            return false;
        }

        return in_array($tournament->getStatus(), [TournamentStatus::ONGOING, TournamentStatus::COMPLETED], true);
    }
}
