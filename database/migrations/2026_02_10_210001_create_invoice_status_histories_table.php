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
        Schema::create('invoice_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->enum('old_status', ['draft', 'sent', 'pending', 'paid'])->nullable();
            $table->enum('new_status', ['draft', 'sent', 'pending', 'paid']);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_status_histories');
    }
};
