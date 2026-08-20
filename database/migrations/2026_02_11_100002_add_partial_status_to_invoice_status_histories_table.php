<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enum widening is a MySQL concern; on other drivers (sqlite, used by
        // the test suite) these columns are plain text and need no change.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE invoice_status_histories MODIFY COLUMN old_status ENUM('draft', 'sent', 'pending', 'partial', 'paid') NULL");
        DB::statement("ALTER TABLE invoice_status_histories MODIFY COLUMN new_status ENUM('draft', 'sent', 'pending', 'partial', 'paid') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Enum widening is a MySQL concern; on other drivers (sqlite, used by
        // the test suite) these columns are plain text and need no change.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE invoice_status_histories MODIFY COLUMN old_status ENUM('draft', 'sent', 'pending', 'paid') NULL");
        DB::statement("ALTER TABLE invoice_status_histories MODIFY COLUMN new_status ENUM('draft', 'sent', 'pending', 'paid') NOT NULL");
    }
};
