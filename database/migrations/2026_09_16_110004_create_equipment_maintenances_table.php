<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One service, planned or one-off: scheduled by date and/or meter, then
     * started and completed. Its cost is never stored — it is the sum of the
     * expenses tagged to it. The `notified_*` stamps are the reminder stages.
     */
    public function up(): void
    {
        Schema::create('equipment_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('equipment_maintenance_plans')->nullOnDelete();
            $table->string('maintenance_type', 20)->default('preventive'); // preventive|corrective|inspection
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled|in_progress|completed|cancelled
            $table->date('scheduled_date')->nullable();
            $table->decimal('due_meter', 12, 1)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('meter_at_completion', 12, 1)->nullable();
            $table->string('performed_by', 10)->nullable(); // internal|vendor
            $table->foreignId('supplier_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('notified_30_at')->nullable();
            $table->timestamp('notified_7_at')->nullable();
            $table->timestamp('notified_due_at')->nullable();
            $table->timestamp('notified_overdue_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_date']);
            $table->index(['equipment_id', 'status']);
        });

        Schema::table('equipment_meter_readings', function (Blueprint $table) {
            $table->foreign('equipment_maintenance_id')->references('id')->on('equipment_maintenances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_meter_readings', function (Blueprint $table) {
            $table->dropForeign(['equipment_maintenance_id']);
        });

        Schema::dropIfExists('equipment_maintenances');
    }
};
