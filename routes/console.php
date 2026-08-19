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
