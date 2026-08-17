<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_measurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_measurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
            $table->unsignedBigInteger('scheduled_amount')->default(0);
            $table->decimal('previous_percent', 5, 2)->default(0);
            $table->decimal('current_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('period_amount')->default(0);
            $table->timestamps();

            $table->index('contract_measurement_id');
            $table->index('budget_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_measurement_items');
    }
};
