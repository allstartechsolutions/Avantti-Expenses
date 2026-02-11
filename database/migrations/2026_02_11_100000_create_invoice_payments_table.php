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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Payment identification
            $table->unsignedInteger('payment_number');

            // Amount stored in cents
            $table->unsignedBigInteger('amount');

            // Payment details
            $table->enum('payment_method', ['cash', 'check', 'credit_card', 'debit_card', 'bank_transfer', 'pix', 'other']);
            $table->date('payment_date');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'voided'])->default('completed');

            // References
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            // Gateway fields (for future CardPointe integration)
            $table->enum('gateway', ['cardpointe', 'manual'])->default('manual');
            $table->string('gateway_transaction_id')->nullable();
            $table->string('gateway_auth_code')->nullable();
            $table->string('gateway_status')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand')->nullable();

            // Refund fields
            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_transaction_id')->nullable();

            // Tracking
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('status');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
