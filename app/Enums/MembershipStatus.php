<?php

namespace App\Enums;

/**
 * Where a person stands on one project or job site.
 */
enum MembershipStatus: string
{
    /** Invited, has not accepted yet — grants nothing until they do. */
    case INVITED = 'invited';

    /** On the project, abilities live. */
    case ACTIVE = 'active';

    /** Kept on the list with their history, but grants nothing. */
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invited',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INVITED => 'amber',
            self::ACTIVE => 'green',
            self::SUSPENDED => 'gray',
        };
    }

    /** Only an active membership hands out abilities. */
    public function grantsAccess(): bool
    {
        return $this === self::ACTIVE;
    }
}
