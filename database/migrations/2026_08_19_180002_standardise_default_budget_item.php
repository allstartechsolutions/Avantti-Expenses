<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One catch-all bucket per budget, resolved by the `is_default` flag.
     *
     * Until now expenses and purchase orders fell into a cost code hardcoded as
     * '999999 Miscellaneous', while contracts fell into whichever item carried
     * `is_default` — two different unassigned buckets inside the same budget.
     * The flag wins; the hardcoded code is retired.
     *
     * This migration only adopts what is already there. It never creates a
     * bucket in a budget that has no use for one (BudgetService seeds it on
     * first use), and it never moves a transaction.
     */
    public function up(): void
    {
        $budgetIds = DB::table('budget_items')->distinct()->pluck('budget_id');

        foreach ($budgetIds as $budgetId) {
            $defaults = DB::table('budget_items')
                ->where('budget_id', $budgetId)
                ->where('is_default', true)
                ->orderBy('id')
                ->pluck('id');

            // More than one default is ambiguous: the oldest one stays.
            if ($defaults->count() > 1) {
                DB::table('budget_items')
                    ->whereIn('id', $defaults->slice(1)->all())
                    ->update(['is_default' => false, 'updated_at' => now()]);

                continue;
            }

            if ($defaults->count() === 1) {
                continue;
            }

            // No default yet: the legacy Miscellaneous code becomes it, so every
            // expense already sitting in that bucket stays exactly where it is.
            $legacyId = DB::table('budget_items')
                ->where('budget_id', $budgetId)
                ->where('code', '999999')
                ->orderBy('id')
                ->value('id');

            if ($legacyId) {
                DB::table('budget_items')
                    ->where('id', $legacyId)
                    ->update(['is_default' => true, 'updated_at' => now()]);
            }
        }
    }

    /**
     * Not reversible: which flags this migration set cannot be told apart from
     * the ones that were already there.
     */
    public function down(): void
    {
        //
    }
};
