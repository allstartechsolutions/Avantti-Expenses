<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case CREATED = 'created';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::CREATED => 'Created',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CREATED => 'blue',
            self::IN_PROGRESS => 'orange',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
        };
    }
}
