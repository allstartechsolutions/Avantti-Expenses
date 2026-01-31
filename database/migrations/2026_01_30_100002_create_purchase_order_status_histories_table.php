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
        Schema::create('purchase_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->enum('old_status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->nullable();
            $table->enum('new_status', ['draft', 'pending', 'approved', 'rejected', 'cancelled']);
            $table->foreignId('changed_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->json('revision_data')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_status_histories');
    }
};
