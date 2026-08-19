<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Contracts gain a `draft` status.
     *
     * A contract raised from a quotation award is not yet a commitment: the
     * dates, the retention and the payment schedule still have to be set. It
     * starts as a draft and is activated deliberately, the same way a purchase
     * order is approved.
     *
     * Nothing existing changes — `active` stays the default, so every current
     * row and every hand-created contract behaves exactly as before.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('draft', 'active', 'completed', 'partially_paid', 'paid', 'cancelled') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        // Anything left in draft becomes active again, otherwise the column
        // would refuse to narrow.
        DB::table('contracts')->where('status', 'draft')->update(['status' => 'active']);

        DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('active', 'completed', 'partially_paid', 'paid', 'cancelled') NOT NULL DEFAULT 'active'");
    }
};
