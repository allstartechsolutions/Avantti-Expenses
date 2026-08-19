<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A split award needs a winner per line, not just per round: the steel to
     * one vendor and the concrete to another is normal practice, and phase 7
     * turns each winning vendor into their own purchase order or contract.
     *
     * Null means the line was not awarded — which is a real outcome when
     * nobody quoted it.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('awarded_quotation_vendor_id')->nullable()->after('quotation_id')
                ->constrained('quotation_vendors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('awarded_quotation_vendor_id');
        });
    }
};
