<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How much of each ordered line has actually arrived (M9).
     *
     * Kept on the line rather than derived from the receipt rows every time,
     * because the outstanding figure is read on every list and detail screen
     * and the receipts are only read when somebody opens the history. The two
     * are written in the same transaction, so they cannot drift.
     *
     * Every existing line starts at zero — an order raised before this is
     * "awaiting delivery", which is the truthful answer for a system that was
     * not recording deliveries.
     */
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('received_quantity', 12, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('received_quantity');
        });
    }
};
