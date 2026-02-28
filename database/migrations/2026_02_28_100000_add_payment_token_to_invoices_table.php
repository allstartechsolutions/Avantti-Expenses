<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_token', 36)->nullable()->unique()->after('notes');
        });

        // Make changed_by nullable for guest payments
        Schema::table('invoice_status_histories', function (Blueprint $table) {
            $table->foreignId('changed_by')->nullable()->change();
        });

        // Backfill existing invoices with tokens
        \App\Models\Invoice::whereNull('payment_token')->each(function ($invoice) {
            $invoice->updateQuietly(['payment_token' => Str::uuid()->toString()]);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_token');
        });

        Schema::table('invoice_status_histories', function (Blueprint $table) {
            $table->foreignId('changed_by')->nullable(false)->change();
        });
    }
};
