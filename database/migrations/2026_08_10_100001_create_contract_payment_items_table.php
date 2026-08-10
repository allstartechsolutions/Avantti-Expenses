<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->decimal('percent_complete', 5, 2)->nullable();
            $table->timestamps();

            $table->index('contract_payment_id');
            $table->index('budget_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_payment_items');
    }
};
