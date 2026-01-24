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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();

            // Item details (stored snapshot for catalog items, or manual entry for custom items)
            $table->string('item_name');
            $table->enum('item_type', ['product', 'service', 'rental'])->nullable();
            $table->string('usage_unit')->nullable(); // For products

            // Pricing
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);

            // Additional info
            $table->text('notes')->nullable();
            $table->date('expense_date');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
