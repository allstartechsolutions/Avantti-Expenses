<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The back-link from what gets paid to the round that decided it.
     *
     * A split award produces one order per winning vendor, so the round cannot
     * point at a single record; these columns are the reliable direction, and
     * `quotations.converted_*` stays as the shortcut for the ordinary
     * one-winner case.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('supplier_id')
                ->constrained('quotations')->nullOnDelete();
            $table->index('quotation_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('subcontractor_id')
                ->constrained('quotations')->nullOnDelete();
            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
        });
    }
};
