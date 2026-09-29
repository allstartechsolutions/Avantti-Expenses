<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail of a contract's own terms: edits to the contract, its
     * change orders and payments taken back out. Status moves keep their own
     * table (contract_status_histories); this one records what changed the
     * money underneath them.
     *
     * The change order is referenced loosely — no foreign key — because the
     * entry that records its deletion must outlive it. Its title and amount
     * are copied into `changes` for the same reason.
     */
    public function up(): void
    {
        Schema::create('contract_change_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('contract_change_order_id')->nullable()->index();
            $table->string('action', 40);
            $table->json('changes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contract_id', 'created_at']);
        });

        // A status corrected by `contracts:reconcile-status` has no person
        // behind it; the history already shows such an entry as "System".
        Schema::table('contract_status_histories', function (Blueprint $table) {
            $table->foreignId('changed_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows written by the system keep their null; tightening the column
        // back would fail on them, so it is left nullable.
        Schema::dropIfExists('contract_change_histories');
    }
};
