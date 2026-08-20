<?php

namespace App\Enums;

/**
 * How much of the system a user can reach at all
 * (docs/permissions-module-plan.md §2).
 */
enum AccessScope: string
{
    /** Sees every project and job site, subject to their role's abilities. */
    case COMPANY = 'company';

    /** Sees only the projects and job sites they hold a membership on. */
    case ASSIGNED = 'assigned';

    public function label(): string
    {
        return match ($this) {
            // The same words wherever this appears — badge, dropdown, radio —
            // so nobody has to work out that three phrases mean one setting.
            self::COMPANY => 'Every project and job site',
            self::ASSIGNED => 'Only the ones they are added to',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::COMPANY => 'Can open every project and job site in the system.',
            self::ASSIGNED => 'Can only open the projects and job sites they have been added to.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::COMPANY => 'blue',
            self::ASSIGNED => 'amber',
        };
    }
}
