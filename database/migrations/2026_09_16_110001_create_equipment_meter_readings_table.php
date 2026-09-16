<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Odometer or hour-meter readings; the newest one is denormalised onto `equipment.current_meter`. */
    public function up(): void
    {
        Schema::create('equipment_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->decimal('reading', 12, 1);
            $table->date('read_at');
            $table->string('source', 20)->default('manual'); // manual|maintenance
            $table->unsignedBigInteger('equipment_maintenance_id')->nullable(); // FK added once that table exists
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipment_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_meter_readings');
    }
};
