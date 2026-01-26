<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Payment status
            $table->enum('status', ['unpaid', 'partial', 'paid', 'overdue', 'cancelled'])
                ->default('unpaid')
                ->after('expense_date');

            // Payment method
            $table->enum('payment_method', ['cash', 'check', 'credit_card', 'debit_card', 'bank_transfer', 'pix', 'other'])
                ->nullable()
                ->after('status');

            // Auto payment flag
            $table->boolean('is_auto_payment')
                ->default(false)
                ->after('payment_method');

            // Installment settings
            $table->unsignedInteger('total_installments')
                ->default(1)
                ->after('is_auto_payment');

            $table->enum('payment_frequency', ['weekly', 'biweekly', 'monthly'])
                ->nullable()
                ->after('total_installments');

            // Payment dates
            $table->date('payment_due_date')
                ->nullable()
                ->after('payment_frequency');

            $table->date('paid_date')
                ->nullable()
                ->after('payment_due_date');
        });

        // Backfill existing expenses as paid (they were already entered without this system)
        DB::table('expenses')->update([
            'status' => 'paid',
            'total_installments' => 1,
        ]);

        // Set paid_date to expense_date for existing records
        DB::table('expenses')->whereNull('paid_date')->update([
            'paid_date' => DB::raw('expense_date'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'payment_method',
                'is_auto_payment',
                'total_installments',
                'payment_frequency',
                'payment_due_date',
                'paid_date',
            ]);
        });
    }
};
