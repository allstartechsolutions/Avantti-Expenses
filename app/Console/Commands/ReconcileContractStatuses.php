<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds contracts whose status disagrees with their money — the ones left
 * behind before an edit of the contract amount re-derived the status (a
 * contract still "Paid" after its price went up). Lists them by default;
 * `--fix` corrects them, each move written to the status history.
 *
 * Only contracts with payments recorded are corrected. A contract marked
 * paid by hand with no payment in the system may have been settled outside
 * it, so it is listed for a person to decide, never changed here.
 */
class ReconcileContractStatuses extends Command
{
    protected $signature = 'contracts:reconcile-status {--fix : Correct the statuses instead of only listing them}';

    protected $description = 'List (or fix with --fix) contracts whose status does not match what was paid';

    public function handle(): int
    {
        $rows = [];
        $fixed = 0;

        Contract::query()
            ->whereNotIn('status', Contract::UNCOMMITTED_STATUSES)
            ->with(['payments', 'changeOrders'])
            ->chunkById(200, function ($contracts) use (&$rows, &$fixed) {
                foreach ($contracts as $contract) {
                    $expected = $contract->statusFromMoney();

                    if ($expected === $contract->status) {
                        continue;
                    }

                    $paid = $contract->getAmountPaid();
                    $fixable = $paid > 0.009;

                    $rows[] = [
                        $contract->contract_number,
                        $contract->status,
                        $expected,
                        number_format($contract->getAdjustedAmount(), 2, '.', ''),
                        number_format($paid, 2, '.', ''),
                        $fixable ? ($this->option('fix') ? 'fixed' : 'will fix') : 'review by hand',
                    ];

                    if ($fixable && $this->option('fix')) {
                        DB::transaction(function () use ($contract) {
                            $oldStatus = $contract->status;
                            $newStatus = $contract->updateStatusFromPayments('Corrected: the status did not match the payments recorded');

                            if ($newStatus) {
                                $contract->recordChange('status_reconciled', [
                                    'status' => ['old' => $oldStatus, 'new' => $newStatus],
                                    'adjusted_amount' => $contract->getAdjustedAmount(),
                                    'amount_paid' => $contract->getAmountPaid(),
                                ]);
                            }
                        });
                        $fixed++;
                    }
                }
            });

        if ($rows === []) {
            $this->info('Every contract status matches its payments.');

            return self::SUCCESS;
        }

        $this->table(['Contract', 'Status', 'Should be', 'Adjusted amount', 'Paid', 'Action'], $rows);

        $this->option('fix')
            ? $this->info("Corrected {$fixed} contract(s).")
            : $this->comment('Nothing changed. Run again with --fix to correct the contracts marked "will fix".');

        return self::SUCCESS;
    }
}
