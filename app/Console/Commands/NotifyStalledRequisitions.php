<?php

namespace App\Console\Commands;

use App\Services\ProcurementNotifier;
use Illuminate\Console\Command;

/**
 * Chases the two places a requisition goes quiet.
 *
 * **Waiting for a decision** — submitted N days ago and still unanswered; goes
 * to whoever would have been asked in the first place. **Approved but
 * unquoted** — handed to a buyer N days ago with no round raised; goes to that
 * buyer, copying whoever approved it.
 *
 * Repeats every N days while it is still stalled, capped at `max_reminders` —
 * both configurable in System Settings. Idempotent through the notification
 * log's window key, so running it twice in one day mails nobody twice.
 * Scheduled daily in routes/console.php.
 */
class NotifyStalledRequisitions extends Command
{
    protected $signature = 'procurement:notify-stalled';

    protected $description = 'Chase requisitions that are stuck — waiting for a decision, or approved with no quotation round';

    public function handle(ProcurementNotifier $notifier): int
    {
        // Two stalls, one command: both answer "nothing is happening to this",
        // one before the decision and one after. A second cron entry would be
        // a second thing to notice has stopped running.
        $awaiting = $notifier->sendAwaitingApproval();
        $stalled = $notifier->sendStalled();

        $this->info(sprintf(
            '%d requisition(s) waiting on a decision, %d approved but unquoted; %d e-mail(s) sent.',
            $awaiting['requisitions'],
            $stalled['requisitions'],
            $awaiting['sent'] + $stalled['sent'],
        ));

        return self::SUCCESS;
    }
}
