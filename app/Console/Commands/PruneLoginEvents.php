<?php

namespace App\Console\Commands;

use App\Models\LoginEvent;
use Illuminate\Console\Command;

/** Drops login activity older than the retention window (docs/login-activity.md). */
class PruneLoginEvents extends Command
{
    protected $signature = 'auth:prune-login-events';

    protected $description = 'Delete login activity older than '.LoginEvent::RETENTION_DAYS.' days';

    public function handle(): int
    {
        $deleted = LoginEvent::where('created_at', '<', now()->subDays(LoginEvent::RETENTION_DAYS))->delete();

        $this->info("Deleted {$deleted} login events.");

        return self::SUCCESS;
    }
}
