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
        Schema::create('catalog_item_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->decimal('old_cost', 10, 2);
            $table->decimal('new_cost', 10, 2);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');
            $table->text('notes')->nullable();

            $table->index(['catalog_item_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_item_price_history');
    }
};
