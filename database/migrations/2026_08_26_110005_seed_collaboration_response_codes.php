<?php

use Database\Seeders\CollaborationResponseCodeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Put the coded responses in place.
     *
     * Reference data, not a schema change, but it arrives here because
     * `php artisan migrate` is the one command every deploy runs — a seeder
     * that has to be remembered separately is a module that works on the
     * developer's machine. The seeder matches on the natural key, so this is
     * safe to run again.
     */
    public function up(): void
    {
        (new CollaborationResponseCodeSeeder)->run();
    }

    public function down(): void
    {
        DB::table('collaboration_response_codes')->whereNull('project_id')->delete();
    }
};
