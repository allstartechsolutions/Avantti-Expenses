<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roughly what the answer is going to cost.
     *
     * `cost_impact` on its own was a flag that revealed nothing, while
     * `schedule_impact` revealed a day count — so the screen asked "does this
     * cost money?" and then had nowhere to put the answer. A rough figure at
     * RFI time is what the change order is argued from later.
     *
     * Signed cents, like `change_orders.amount` (docs/monetary-storage.md): a
     * credit is as real an impact as a cost, and storing reais as a float is
     * how rounding errors get into a budget.
     *
     * Nullable and blank on every existing row, so nothing changes for a
     * record already raised.
     */
    public function up(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->bigInteger('cost_impact_amount')->nullable()->after('cost_impact');
        });
    }

    public function down(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->dropColumn('cost_impact_amount');
        });
    }
};
