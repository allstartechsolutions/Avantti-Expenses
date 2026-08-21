<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliveries against a purchase order (M9).
     *
     * One `purchase_order_receipts` row per delivery — the day the lorry came,
     * who signed for it, and anything worth saying about it. The line rows say
     * what was on that delivery, which is what makes a part-delivery honest:
     * "40 bars ordered, 25 came on the 21st, 15 outstanding" reads as three
     * facts rather than one number that quietly changed.
     */
    public function up(): void
    {
        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->date('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['purchase_order_id', 'received_at']);
        });

        Schema::create('purchase_order_receipt_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_receipt_id')
                ->constrained('purchase_order_receipts')
                ->cascadeOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->constrained('purchase_order_items')
                ->cascadeOnDelete();

            $table->decimal('quantity', 12, 2);

            $table->timestamps();

            $table->index('purchase_order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_lines');
        Schema::dropIfExists('purchase_order_receipts');
    }
};
