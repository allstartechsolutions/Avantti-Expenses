<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Something encountered during a maintenance, open until a later one (or a note) resolves it. */
    public function up(): void
    {
        Schema::create('equipment_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('equipment_maintenance_id')->constrained('equipment_maintenances')->cascadeOnDelete();
            $table->text('description');
            $table->string('severity', 10)->default('medium'); // low|medium|high|critical
            $table->string('status', 10)->default('open'); // open|resolved
            $table->string('photo_path')->nullable();
            $table->foreignId('resolved_in_maintenance_id')->nullable()->constrained('equipment_maintenances')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_findings');
    }
};
