<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('description');
            $table->enum('trigger_type', ['date', 'milestone'])->default('milestone');
            $table->date('due_date')->nullable();
            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
            // Exactly one of percent / amount is set: percent-based parcelas
            // compute their value from the adjusted contract amount at read
            // time so change orders re-flow automatically.
            $table->decimal('percent', 5, 2)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('contract_id');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_schedule_items');
    }
};
