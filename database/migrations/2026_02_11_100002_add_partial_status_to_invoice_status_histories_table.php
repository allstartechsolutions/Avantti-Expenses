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
        DB::statement("ALTER TABLE invoice_status_histories MODIFY COLUMN old_status ENUM('draft', 'sent', 'pending', 'partial', 'paid') NULL");
        DB::statement("ALTER TABLE invoice_status_histories MODIFY COLUMN new_status ENUM('draft', 'sent', 'pending', 'partial', 'paid') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE invoice_status_histories MODIFY COLUMN old_status ENUM('draft', 'sent', 'pending', 'paid') NULL");
        DB::statement("ALTER TABLE invoice_status_histories MODIFY COLUMN new_status ENUM('draft', 'sent', 'pending', 'paid') NOT NULL");
    }
};
