<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every status move on a requisition, kept for audit — same shape as
     * purchase_order_status_histories.
     */
    public function up(): void
    {
        Schema::create('purchase_requisition_status_histories', function (Blueprint $table) {
            $table->id();
            // Short, explicit constraint/index names: the generated ones exceed
            // MySQL's 64-character identifier limit for this table name.
            $table->foreignId('purchase_requisition_id');
            $table->foreign('purchase_requisition_id', 'preq_status_hist_requisition_fk')
                ->references('id')->on('purchase_requisitions')->cascadeOnDelete();
            $table->enum('old_status', [
                'draft', 'pending', 'approved', 'rejected', 'quoted', 'fulfilled', 'cancelled',
            ])->nullable();
            $table->enum('new_status', [
                'draft', 'pending', 'approved', 'rejected', 'quoted', 'fulfilled', 'cancelled',
            ]);
            $table->foreignId('changed_by');
            $table->foreign('changed_by', 'preq_status_hist_changed_by_fk')
                ->references('id')->on('users');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('purchase_requisition_id', 'preq_status_hist_requisition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_status_histories');
    }
};
