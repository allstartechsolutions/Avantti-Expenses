<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Document repository housekeeping — see docs/file-repository-plan.md.
// Incomplete multipart uploads are billed by Cloudflare R2 until aborted, and
// trashed documents keep occupying storage until the retention window passes.
Schedule::command('documents:prune-uploads')->hourly()->withoutOverlapping();
Schedule::command('documents:purge-deleted')->dailyAt('03:15')->withoutOverlapping();

// Task e-mails (docs/meetings-module-plan.md §8). Both are idempotent: the
// notification log stops anybody being mailed twice, so a double run is safe.
Schedule::command('tasks:notify-overdue')->dailyAt('07:00')->withoutOverlapping();

// Hourly, and the command decides whether this is the configured day and hour —
// so moving the digest in System Settings takes effect without a deploy.
Schedule::command('tasks:send-weekly-digest')->hourly()->withoutOverlapping();
