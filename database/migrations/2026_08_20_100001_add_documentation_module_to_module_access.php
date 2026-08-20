<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Register Documentation so an admin can switch it off from System
     * Settings like every other non-core module.
     */
    public function up(): void
    {
        // No users yet — a fresh install before the setup wizard, or the test
        // suite. module_access.created_by is a non-nullable foreign key, and a
        // missing row already reads as enabled, so there is nothing to do here.
        if (DB::table('users')->doesntExist()) {
            return;
        }

        $module = config('modules.documentation');

        if (! $module) {
            return;
        }

        DB::table('module_access')->insertOrIgnore([
            'module_key' => 'documentation',
            'module_name' => $module['name'],
            'description' => $module['description'] ?? null,
            'is_enabled' => true,
            'is_core' => false,
            'created_by' => DB::table('users')->orderBy('id')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('module_access')->where('module_key', 'documentation')->delete();
    }
};
