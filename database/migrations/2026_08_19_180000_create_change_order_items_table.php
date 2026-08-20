<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cost side of a project / job site change order: how much the change
     * adds to (or takes off) each cost code's budget. The change order's own
     * `amount` stays what it always was — the revenue side, what the client is
     * billed — so every existing report keeps its numbers.
     */
    public function up(): void
    {
        Schema::create('change_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            // Signed cents: positive adds budget to the code, negative takes it away.
            $table->bigInteger('amount')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['change_order_id', 'sort_order']);
            $table->index('budget_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_order_items');
    }
};
