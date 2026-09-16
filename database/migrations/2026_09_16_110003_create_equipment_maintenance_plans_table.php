<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recurring service: every N days and/or every N km / hours, whichever
     * comes first. Exactly one open maintenance row exists per active plan —
     * the next occurrence — generated from the completion point of the last.
     */
    public function up(): void
    {
        Schema::create('equipment_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('maintenance_type', 20)->default('preventive'); // preventive|inspection
            $table->unsignedInteger('interval_days')->nullable();
            $table->unsignedInteger('interval_meter')->nullable();
            $table->unsignedInteger('meter_lead')->nullable(); // "due soon" margin on the meter
            $table->boolean('is_active')->default(true);
            $table->date('last_completed_date')->nullable();
            $table->decimal('last_completed_meter', 12, 1)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipment_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_plans');
    }
};
