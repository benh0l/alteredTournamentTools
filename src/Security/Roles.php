<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Role constants for the application.
 *
 * Role Hierarchy:
 * - ROLE_USER: Base role for all authenticated users (players)
 * - ROLE_ORGANIZER: Can create and manage tournaments (inherits ROLE_USER)
 * - ROLE_ADMIN: Full system access (inherits ROLE_ORGANIZER)
 */
final class Roles
{
    /**
     * Base role for all authenticated users.
     * Grants: View tournaments, register to tournaments, submit match results.
     */
    public const USER = 'ROLE_USER';

    /**
     * Alias for ROLE_USER for clarity in player contexts.
     */
    public const PLAYER = 'ROLE_PLAYER';

    /**
     * Organizer role - can create and manage tournaments.
     * Grants: Create tournaments, manage registrations, resolve disputes.
     */
    public const ORGANIZER = 'ROLE_ORGANIZER';

    /**
     * Administrator role - full system access.
     * Grants: User management, system configuration, override any action.
     */
    public const ADMIN = 'ROLE_ADMIN';

    /**
     * All valid roles for validation.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::USER,
            self::ORGANIZER,
            self::ADMIN,
        ];
    }

    /**
     * Roles that can be assigned by admin.
     *
     * @return array<string>
     */
    public static function assignable(): array
    {
        return [
            self::ORGANIZER,
            self::ADMIN,
        ];
    }
}
