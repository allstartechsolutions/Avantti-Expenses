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
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['product', 'service', 'rental']);
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);

            // Product-specific fields
            $table->string('purchase_unit')->nullable();
            $table->string('usage_unit')->nullable();
            $table->decimal('units_per_purchase', 10, 2)->nullable();

            // Pricing fields
            $table->decimal('current_cost', 10, 2);
            $table->enum('billing_type', ['hourly', 'fixed', 'daily', 'weekly', 'monthly'])->nullable();

            // Audit fields
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
