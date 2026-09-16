<?php

namespace App\Console\Commands;

use App\Services\EquipmentMaintenanceNotifier;
use Illuminate\Console\Command;

/**
 * Warns the chosen people about equipment maintenance reaching a reminder
 * stage: 30 and 7 days before its date, the day it is due — by date or by
 * meter — and the day after the date passes.
 *
 * Once per stage per maintenance, stamped on the maintenance itself; one
 * e-mail per person per morning. Scheduled daily in routes/console.php.
 */
class NotifyEquipmentMaintenanceDue extends Command
{
    protected $signature = 'equipment:notify-maintenance-due';

    protected $description = 'E-mail the chosen people about equipment maintenance that is coming due, due, or overdue';

    public function handle(EquipmentMaintenanceNotifier $notifier): int
    {
        $result = $notifier->sendDueReminders();

        $this->info(sprintf(
            '%d maintenance(s) at a reminder stage, %d recipient(s); %d e-mail(s) sent.',
            $result['maintenances'],
            $result['recipients'],
            $result['sent'],
        ));

        return self::SUCCESS;
    }
}
