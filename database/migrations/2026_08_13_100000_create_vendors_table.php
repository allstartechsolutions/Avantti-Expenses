<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unified table for suppliers and subcontractors. One company can be
     * classified as both (is_supplier / is_subcontractor flags), so it only
     * has to be entered once. Column superset of the legacy `suppliers` and
     * `subcontractors` tables; the legacy_* columns keep the original row ids
     * for the data migration and for rollback.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_subcontractor')->default(false);

            // Contact person (from subcontractors)
            $table->string('website')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('title')->nullable();

            // Shared contact / details
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();

            // Address
            $table->string('street')->nullable();
            $table->string('address_2')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // System
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('legacy_supplier_id')->nullable();
            $table->unsignedBigInteger('legacy_subcontractor_id')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('is_supplier');
            $table->index('is_subcontractor');
            $table->index('legacy_supplier_id');
            $table->index('legacy_subcontractor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
