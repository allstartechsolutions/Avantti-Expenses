<?php

namespace App\Enums;

enum ProjectAmountSource: string
{
    case MANUAL = 'manual';
    case FROM_JOBSITES = 'from_jobsites';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => __('Manual amount'),
            self::FROM_JOBSITES => __('Calculate from job sites'),
        };
    }
}
