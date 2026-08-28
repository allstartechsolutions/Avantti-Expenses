<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case CREATED = 'created';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Whether moving to this status closes the project down.
     *
     * There is no separate "archived" state: completing a project and
     * cancelling one are both the act the catalogue calls *Archive or close*,
     * and both stop work against it. `project.archive` is what guards moving
     * into either.
     */
    public function closesTheProject(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    public function label(): string
    {
        return match($this) {
            self::CREATED => __('Created'),
            self::IN_PROGRESS => __('In Progress'),
            self::COMPLETED => __('Completed'),
            self::CANCELLED => __('Cancelled'),
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
