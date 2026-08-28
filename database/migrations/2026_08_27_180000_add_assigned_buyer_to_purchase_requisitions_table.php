<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who was told to go and quote this one.
     *
     * The gap this closes: between a requisition being approved and a
     * quotation round being raised, nobody owned the work. `assigned_buyer_id`
     * is the name on that work order and `assigned_at` is when it was handed
     * over — the pair is what the stall reminder in phase 5 counts days from.
     *
     * One person, not many: "you, start a cotação for this" is an instruction,
     * and an instruction addressed to three people is not an instruction. The
     * round itself gets an owner *and* collaborators in phase 4, because
     * collecting quotes is work several people can share.
     *
     * nullOnDelete rather than cascade: deleting a user must not delete the
     * requisition they were asked to buy for. It falls back to unassigned,
     * which is a state the queue shows rather than hides.
     */
    public function up(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->foreignId('assigned_buyer_id')
                ->nullable()
                ->after('reviewed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable()->after('assigned_buyer_id');

            // The queue in phase 6 lists "approved, assigned to me, no round
            // yet" and "approved, assigned to nobody"; both read this pair.
            $table->index(['assigned_buyer_id', 'status'], 'preq_assigned_buyer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->dropIndex('preq_assigned_buyer_status_idx');
            $table->dropConstrainedForeignId('assigned_buyer_id');
            $table->dropColumn('assigned_at');
        });
    }
};
