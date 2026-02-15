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
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->enum('payment_method', [
                'cash', 'check', 'credit_card', 'debit_card', 'bank_transfer', 'pix', 'other',
            ])->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->enum('payment_method', [
                'cash', 'check', 'credit_card', 'debit_card', 'bank_transfer', 'pix', 'other',
            ])->default('check')->change();
        });
    }
};
