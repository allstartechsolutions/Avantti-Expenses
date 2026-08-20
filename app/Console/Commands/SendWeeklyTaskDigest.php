<?php

namespace App\Console\Commands;

use App\Models\NotificationSetting;
use App\Services\TaskNotifier;
use Illuminate\Console\Command;

/**
 * One e-mail per person listing everything of theirs still open.
 *
 * Scheduled hourly and gated on the configured day and hour, so an admin can
 * move it from System Settings without a deploy. Sending it twice in a week is
 * prevented by the notification log, not by the schedule.
 */
class SendWeeklyTaskDigest extends Command
{
    protected $signature = 'tasks:send-weekly-digest {--force : Ignore the configured day and hour}';

    protected $description = 'E-mail everybody a digest of their open tasks';

    public function handle(TaskNotifier $notifier): int
    {
        if (! $this->option('force') && ! $this->isDue()) {
            return self::SUCCESS;
        }

        $result = $notifier->sendWeeklyDigest();

        $this->info(sprintf(
            '%d person(s) with open tasks; %d digest(s) sent.',
            $result['users'],
            $result['sent']
        ));

        return self::SUCCESS;
    }

    private function isDue(): bool
    {
        return now()->dayOfWeekIso === NotificationSetting::digestDay()
            && now()->hour === NotificationSetting::digestHour();
    }
}
