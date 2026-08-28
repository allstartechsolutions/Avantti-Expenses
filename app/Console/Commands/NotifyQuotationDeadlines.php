<?php

namespace App\Console\Commands;

use App\Services\ProcurementNotifier;
use Illuminate\Console\Command;

/**
 * Warns the owner and collaborators of an open quotation round shortly before
 * the response date, and again once it has passed.
 *
 * Once each, per round, using the two stamps on `quotations` — both of which
 * are cleared when the response date moves, so a round whose deadline is
 * pushed can warn again later. Scheduled daily in routes/console.php.
 */
class NotifyQuotationDeadlines extends Command
{
    protected $signature = 'procurement:notify-due';

    protected $description = 'E-mail the people working a quotation round when responses are due or past due';

    public function handle(ProcurementNotifier $notifier): int
    {
        $result = $notifier->sendDueWarnings();

        $this->info(sprintf(
            '%d round(s) approaching their date, %d past due; %d e-mail(s) sent.',
            $result['due'],
            $result['overdue'],
            $result['sent'],
        ));

        return self::SUCCESS;
    }
}
