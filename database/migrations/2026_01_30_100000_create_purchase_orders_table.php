<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();

            // Status workflow
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->unsignedInteger('revision_number')->default(1);

            // PO details
            $table->string('po_number')->nullable();
            $table->date('po_date');
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();

            // Payment fields (carried to expense on approval)
            $table->enum('payment_method', ['cash', 'check', 'credit_card', 'debit_card', 'bank_transfer', 'pix', 'other'])->nullable();
            $table->boolean('is_auto_payment')->default(false);
            $table->unsignedInteger('total_installments')->default(1);
            $table->enum('payment_frequency', ['weekly', 'biweekly', 'monthly'])->nullable();
            $table->date('payment_due_date')->nullable();

            // Totals (stored in cents)
            $table->unsignedBigInteger('total_amount')->default(0);

            // Tracking
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('job_site_id');
            $table->index('status');
            $table->index('po_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
