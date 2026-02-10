<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->boolean('is_taxable')->default(false)->after('billing_type');
            $table->foreignId('tax_rate_id')->nullable()->after('is_taxable')
                ->constrained('tax_rates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['is_taxable', 'tax_rate_id']);
        });
    }
};
