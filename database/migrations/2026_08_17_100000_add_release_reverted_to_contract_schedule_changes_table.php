<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Un-approving a parcela (a mistaken approval, only possible while
     * no payment or medição is linked) is its own auditable action.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE contract_schedule_changes MODIFY COLUMN action ENUM('created', 'updated', 'deleted', 'released', 'release_reverted') NOT NULL");
    }

    public function down(): void
    {
        DB::table('contract_schedule_changes')->where('action', 'release_reverted')->update(['action' => 'updated']);

        DB::statement("ALTER TABLE contract_schedule_changes MODIFY COLUMN action ENUM('created', 'updated', 'deleted', 'released') NOT NULL");
    }
};
