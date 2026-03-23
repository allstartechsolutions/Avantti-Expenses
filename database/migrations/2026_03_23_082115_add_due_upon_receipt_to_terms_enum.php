<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN terms ENUM('due_upon_receipt', 'net_15', 'net_30', 'net_60', 'net_90') NOT NULL");
        DB::statement("ALTER TABLE estimates MODIFY COLUMN terms ENUM('due_upon_receipt', 'net_15', 'net_30', 'net_60', 'net_90') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN terms ENUM('net_15', 'net_30', 'net_60', 'net_90') NOT NULL");
        DB::statement("ALTER TABLE estimates MODIFY COLUMN terms ENUM('net_15', 'net_30', 'net_60', 'net_90') NOT NULL");
    }
};
