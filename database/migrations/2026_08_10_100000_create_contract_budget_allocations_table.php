<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['contract_id', 'budget_item_id']);
            $table->index('budget_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_budget_allocations');
    }
};
