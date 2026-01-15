<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\Roles;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter for User entity permissions.
 *
 * Permissions:
 * - VIEW: Self or ROLE_ADMIN
 * - EDIT: Self only (profile) or ROLE_ADMIN (for user management)
 * - DELETE: Self only (account deletion) or ROLE_ADMIN
 */
final class UserVoter extends Voter
{
    public const VIEW = 'USER_VIEW';
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($targetUser, $currentUser),
            self::EDIT => $this->canEdit($targetUser, $currentUser),
            self::DELETE => $this->canDelete($targetUser, $currentUser),
            default => false,
        };
    }

    private function canView(User $targetUser, User $currentUser): bool
    {
        // Users can view their own profile
        if ($targetUser->getId() === $currentUser->getId()) {
            return true;
        }

        // Admin can view any user
        return $this->isAdmin($currentUser);
    }

    private function canEdit(User $targetUser, User $currentUser): bool
    {
        // Users can only edit their own profile
        if ($targetUser->getId() === $currentUser->getId()) {
            return true;
        }

        // Admin can edit any user
        return $this->isAdmin($currentUser);
    }

    private function canDelete(User $targetUser, User $currentUser): bool
    {
        // Users can delete their own account
        if ($targetUser->getId() === $currentUser->getId()) {
            return true;
        }

        // Admin can delete any user
        return $this->isAdmin($currentUser);
    }

    private function isAdmin(User $user): bool
    {
        return in_array(Roles::ADMIN, $user->getRoles(), true);
    }
}
