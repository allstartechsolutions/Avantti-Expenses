<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Back charges (docs/back-charges.md): money a subcontractor company — or one
 * of its employees — owes, taken out of what they are paid, in full or in
 * parts. Each part is a deduction tied to the contract it was taken from and
 * the batch line or payment that took it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('back_charges', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            // Money blocks a delete; it never cascades (CLAUDE.md).
            $table->foreignId('subcontractor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('subcontractor_employee_id')->nullable()->constrained()->restrictOnDelete();
            // Where the problem happened — a label, the saving lands on the paying contract.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount');
            $table->date('date');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subcontractor_id', 'date']);
            $table->index('job_site_id');
        });

        Schema::create('back_charge_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('back_charge_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            // A deduction is part of the line or payment that took it: it goes with them.
            $table->foreignId('payment_batch_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('contract_payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contract_id', 'contract_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('back_charge_deductions');
        Schema::dropIfExists('back_charges');
    }
};
