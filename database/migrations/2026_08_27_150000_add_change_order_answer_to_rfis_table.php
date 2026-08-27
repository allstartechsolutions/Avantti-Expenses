<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The answer as it read when the aditivo was raised from it.
     *
     * `rfis.change_order_id` already said *which* change order came out of an
     * RFI (though until now nothing ever wrote it). This says **what the
     * change order was argued from** — because an answer can be corrected
     * afterwards by somebody holding `rfis.revise`, and a change order whose
     * justification silently rewrote itself is worth nothing in an argument
     * three months later.
     *
     * Kept on `rfis` rather than added to `change_orders`: this is the
     * collaboration module's fact about its own record, and a core table
     * should not grow a column for every module that points at it.
     */
    public function up(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->text('change_order_answer')->nullable()->after('change_order_id');
            $table->timestamp('change_order_linked_at')->nullable()->after('change_order_answer');
        });
    }

    public function down(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->dropColumn(['change_order_answer', 'change_order_linked_at']);
        });
    }
};
