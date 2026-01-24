<?php

namespace App\Enums;

enum JobSiteStatus: string
{
    case CREATED = 'created';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case ON_HOLD = 'on_hold';

    public function label(): string
    {
        return match($this) {
            self::CREATED => 'Created',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::ON_HOLD => 'On Hold',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CREATED => 'blue',
            self::IN_PROGRESS => 'orange',
            self::COMPLETED => 'green',
            self::ON_HOLD => 'gray',
        };
    }
}
