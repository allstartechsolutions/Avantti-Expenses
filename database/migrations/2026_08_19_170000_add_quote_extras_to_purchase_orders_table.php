<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A quotation's winning proposal can carry freight, tax and a discount on
     * top of its priced lines. The purchase order had nowhere to put them, so
     * the award wrote the grand total into the header while the items held
     * only the lines — and the first save of that PO recomputed the header
     * from the items and lost the difference.
     *
     * All three are stored in cents, like every other monetary column.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('freight_amount')->default(0)->after('total_amount');
            $table->unsignedBigInteger('tax_amount')->default(0)->after('freight_amount');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['freight_amount', 'tax_amount', 'discount_amount']);
        });
    }
};
