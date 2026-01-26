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
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();

            // Payment identification
            $table->unsignedInteger('payment_number');

            // Amount stored in cents (like expenses)
            $table->bigInteger('amount');

            // Dates
            $table->date('due_date');
            $table->date('paid_date')->nullable();

            // Status
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');

            // Payment method (can override expense default)
            $table->enum('payment_method', ['cash', 'check', 'credit_card', 'debit_card', 'bank_transfer', 'pix', 'other'])
                ->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('due_date');
            $table->index('status');
            $table->index(['expense_id', 'payment_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
