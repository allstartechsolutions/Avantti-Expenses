<?php

namespace App\Console\Commands;

use App\Services\VendorDocumentNotifier;
use Illuminate\Console\Command;

/**
 * Warns the chosen people about vendor documents reaching a reminder stage:
 * 30, 15 and 7 days before the expiration date, and the day after it.
 *
 * Once per stage per document, stamped on the document itself; one e-mail
 * per person per morning. Scheduled daily in routes/console.php.
 */
class NotifyVendorDocumentExpiry extends Command
{
    protected $signature = 'vendors:notify-document-expiry';

    protected $description = 'E-mail the chosen people about vendor documents that are expiring or have expired';

    public function handle(VendorDocumentNotifier $notifier): int
    {
        $result = $notifier->sendExpiryReminders();

        $this->info(sprintf(
            '%d document(s) at a reminder stage, %d recipient(s); %d e-mail(s) sent.',
            $result['documents'],
            $result['recipients'],
            $result['sent'],
        ));

        return self::SUCCESS;
    }
}
