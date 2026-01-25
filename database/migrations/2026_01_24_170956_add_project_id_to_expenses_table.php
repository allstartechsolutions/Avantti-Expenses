<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration:
     * 1. Adds project_id column to expenses (required)
     * 2. Backfills project_id from existing job_site relationships
     * 3. Makes job_site_id nullable (for project-level expenses)
     */
    public function up(): void
    {
        // Step 1: Add project_id column (nullable initially for backfill)
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        // Step 2: Backfill project_id from job_site relationships
        DB::statement('
            UPDATE expenses
            SET project_id = (
                SELECT project_id FROM job_sites WHERE job_sites.id = expenses.job_site_id
            )
            WHERE project_id IS NULL
        ');

        // Step 3: Make project_id required (after backfill)
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });

        // Step 4: Make job_site_id nullable
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('job_site_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This will fail if there are expenses without job_site_id
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('job_site_id')->nullable(false)->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
