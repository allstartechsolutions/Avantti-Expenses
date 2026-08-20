<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Allow a payment to be partially refunded (one or more times).
        // Enum widening is a MySQL concern; on sqlite the column is plain text.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'refunded', 'voided', 'partially_refunded') NOT NULL DEFAULT 'completed'");
        }

        // Log table: one row per void/refund issued against a payment.
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_payment_id')->constrained()->cascadeOnDelete();

            // Amount of this individual refund/void, stored in cents.
            $table->unsignedBigInteger('amount');
            $table->enum('type', ['refund', 'void'])->default('refund');

            // Gateway response details for this refund/void.
            $table->string('gateway_transaction_id')->nullable();
            $table->string('respstat')->nullable();
            $table->string('resptext')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('invoice_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');

        // Collapse any partially refunded payments back to a supported status.
        DB::table('invoice_payments')->where('status', 'partially_refunded')->update(['status' => 'completed']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'refunded', 'voided') NOT NULL DEFAULT 'completed'");
        }
    }
};
