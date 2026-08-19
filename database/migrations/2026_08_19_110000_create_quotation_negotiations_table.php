<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One round of haggling with one vendor.
     *
     * Negotiating rewrites the prices, so without this the fact that a vendor
     * came down from 48k to 41k over two rounds would be lost the moment the
     * new numbers were keyed in. The buyer's work is part of the record.
     */
    public function up(): void
    {
        Schema::create('quotation_negotiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_vendor_id')->constrained('quotation_vendors')->cascadeOnDelete();
            $table->unsignedInteger('round')->default(1);

            // Equalized totals in cents, before and after this round
            $table->unsignedBigInteger('previous_total')->default(0);
            $table->unsignedBigInteger('new_total')->default(0);

            $table->text('note');
            $table->foreignId('negotiated_by')->constrained('users');
            $table->timestamp('negotiated_at');
            $table->timestamps();

            $table->unique(['quotation_vendor_id', 'round'], 'quotation_negotiation_round_unique');
            $table->index('quotation_vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_negotiations');
    }
};
