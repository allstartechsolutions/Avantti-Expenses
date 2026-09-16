<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An expense may now belong to the company rather than a project.
     *
     * `project_id` was made NOT NULL by 2026_01_24_170956; a row with it
     * null is a **company (general) expense** — rent, utilities, insurance —
     * which carries an `expense_category_id` instead of a job site and a
     * cost code. The rule is enforced by `Expense::booted()` on every driver
     * and, on MySQL, by a CHECK as belt-and-braces:
     *
     *   company row: project_id NULL, job_site_id NULL, category NOT NULL
     *   project row: project_id NOT NULL, category NULL
     *
     * The three indexes are the ones every list and report already filters
     * by; `expenses` had none at all.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Restating only `nullable()`: Laravel keeps nothing else on a
            // change(), and the foreign key stands as it was.
            $table->foreignId('project_id')->nullable()->change();

            $table->index(['project_id', 'expense_date'], 'expenses_project_date_index');
            $table->index(['expense_category_id', 'expense_date'], 'expenses_category_date_index');
            $table->index(['status', 'payment_due_date'], 'expenses_status_due_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE expenses ADD CONSTRAINT chk_expenses_scope CHECK (
                (project_id IS NULL AND job_site_id IS NULL AND expense_category_id IS NOT NULL)
                OR (project_id IS NOT NULL AND expense_category_id IS NULL)
            )');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE expenses DROP CONSTRAINT chk_expenses_scope');
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_project_date_index');
            $table->dropIndex('expenses_category_date_index');
            $table->dropIndex('expenses_status_due_index');
        });

        // Company rows cannot survive the column going back to NOT NULL.
        DB::table('expenses')->whereNull('project_id')->delete();

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });
    }
};
