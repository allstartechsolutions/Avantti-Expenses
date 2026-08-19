<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The purchase requisition (solicitação de compra) is the start of the
     * buy-side chain: the site says what is needed, a manager or admin
     * approves it, and only then is it quoted.
     */
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->nullOnDelete();

            $table->string('requisition_number')->nullable();
            $table->enum('type', ['material', 'service'])->default('material');
            $table->string('title');
            $table->text('justification')->nullable();
            $table->date('needed_by')->nullable();
            $table->enum('priority', ['low', 'normal', 'urgent'])->default('normal');
            $table->enum('status', [
                'draft', 'pending', 'approved', 'rejected', 'quoted', 'fulfilled', 'cancelled',
            ])->default('draft');

            // Where the eventual spend belongs. Budget items carry the cost
            // code in this app, so the budget item is the only link needed.
            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();

            // Who asked. requested_by_name covers the site person who has no login.
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name')->nullable();

            // Approve / reject audit
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('project_id');
            $table->index('job_site_id');
            $table->index('status');
            $table->index('needed_by');
            $table->index('requisition_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisitions');
    }
};
