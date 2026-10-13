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
            self::CREATED => __('Created'),
            self::IN_PROGRESS => __('In Progress'),
            self::COMPLETED => __('Completed'),
            self::ON_HOLD => __('On Hold'),
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
