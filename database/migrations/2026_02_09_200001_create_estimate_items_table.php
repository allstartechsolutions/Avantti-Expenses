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
        Schema::create('estimate_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('item_type', ['catalog', 'custom']);
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('unit_price')->default(0);

            // Per-item discount
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->unsignedBigInteger('discount_amount')->default(0);

            // Tax
            $table->boolean('is_taxable')->default(false);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);

            // Total
            $table->unsignedBigInteger('total_amount')->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('estimate_id');
            $table->index('catalog_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_items');
    }
};
