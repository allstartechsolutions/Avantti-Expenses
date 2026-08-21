<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make every non-MySQL database match what production already looks like.
     *
     * `2026_08_13_100001` — the vendor unification — drops the foreign keys
     * that pointed `supplier_id` / `subcontractor_id` at the legacy `suppliers`
     * and `subcontractors` tables, then remaps the ids into `vendors`. Its body
     * returns early on any other driver, correctly: there are no legacy rows to
     * move. But the *constraints* were still there, created by the original
     * table migrations, so on sqlite those columns still point at tables the
     * application stopped writing to.
     *
     * The visible symptom was a foreign-key error when an awarded quotation was
     * converted into a purchase order for a vendor with no legacy row — a
     * failure that cannot happen in production and made the conversion path
     * impossible to cover with a test (docs/review-and-improvements.md, P12).
     *
     * MySQL is skipped because the unification already did this there, and
     * dropping a constraint that is gone would fail.
     */
    private const SUPPLIER_FKS = ['expenses', 'catalog_items', 'purchase_orders'];

    private const SUBCONTRACTOR_FKS = [
        'contracts', 'payment_batches', 'subcontractor_documents', 'subcontractor_employees',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            return;
        }

        foreach (self::SUPPLIER_FKS as $table) {
            $this->dropForeignIfPossible($table, 'supplier_id');
        }

        foreach (self::SUBCONTRACTOR_FKS as $table) {
            $this->dropForeignIfPossible($table, 'subcontractor_id');
        }
    }

    /**
     * Drop one foreign key, tolerating a table or column that is not there and
     * a constraint that was never created. Nothing here is worth failing a
     * deploy over: the column keeps its data either way.
     */
    private function dropForeignIfPossible(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // No such constraint, or a driver that will not drop it. Either way
            // the column is unchanged and the application is unaffected.
        }
    }

    public function down(): void
    {
        // Deliberately empty. Putting the constraints back would point these
        // columns at tables the application no longer writes to.
    }
};
