<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('measurement_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            // Snapshots taken at approval; the contract's retention_percent
            // may change later without affecting approved measurements.
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('retention_amount')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->foreignId('contract_schedule_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'measurement_number']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_measurements');
    }
};
