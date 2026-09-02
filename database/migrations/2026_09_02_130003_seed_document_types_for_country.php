<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Superseded before it shipped: the country seed now runs from
     * 2026_09_02_130004, after the stable `key` column exists. Kept so the
     * migration table on an install that already ran it stays consistent.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
