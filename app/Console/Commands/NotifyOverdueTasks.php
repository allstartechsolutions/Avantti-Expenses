<?php

namespace App\Console\Commands;

use App\Services\TaskNotifier;
use Illuminate\Console\Command;

/**
 * Tells owners and assignees about tasks that went past their date.
 *
 * Once per task, not once per morning: the weekly digest carries them after
 * that. Scheduled daily in routes/console.php.
 */
class NotifyOverdueTasks extends Command
{
    protected $signature = 'tasks:notify-overdue';

    protected $description = 'E-mail the owner and assignees of tasks that have just gone past due';

    public function handle(TaskNotifier $notifier): int
    {
        $result = $notifier->sendOverdue();

        $this->info(sprintf(
            '%d task(s) newly past due; %d e-mail(s) sent.',
            $result['tasks'],
            $result['sent']
        ));

        return self::SUCCESS;
    }
}
