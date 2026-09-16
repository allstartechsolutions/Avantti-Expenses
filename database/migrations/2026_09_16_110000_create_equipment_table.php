<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The asset register: vehicles, machinery and tools the company owns,
     * leases or rents. Not a catalog item — the catalog is what is bought;
     * this is what is kept, maintained and assigned. docs/equipment-module.md
     */
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            // Identity
            $table->string('name')->index();
            $table->string('asset_tag', 60)->nullable()->index();
            $table->string('equipment_type', 20)->default('machinery'); // vehicle|machinery|tool|other
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('plate', 20)->nullable()->index();
            $table->string('vin', 40)->nullable();
            // Ownership and purchase
            $table->string('ownership', 10)->default('owned'); // owned|leased|rented
            $table->foreignId('supplier_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('purchase_cost')->nullable(); // cents
            $table->date('warranty_until')->nullable();
            // Meter
            $table->string('meter_type', 10)->default('none'); // none|km|mi|hours
            $table->decimal('current_meter', 12, 1)->nullable();
            $table->date('current_meter_at')->nullable();
            // Status
            $table->string('status', 20)->default('active'); // active|in_maintenance|retired|sold
            $table->date('retired_at')->nullable();
            // Current assignment (history in equipment_assignments)
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assigned_at')->nullable();
            // Files and notes
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'equipment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
