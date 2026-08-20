<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // module_access.created_by is a non-nullable foreign key, so there is
        // nobody to attribute these rows to on a database with no users yet —
        // a fresh install before the setup wizard, or the test suite. Skipping
        // is safe: ModuleAccess::isEnabled() treats a missing row as enabled,
        // and the settings screen writes the row when a module is first toggled.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId === null) {
            return;
        }

        $now = now();

        $modules = config('modules');

        foreach ($modules as $key => $module) {
            DB::table('module_access')->insertOrIgnore([
                'module_key' => $key,
                'module_name' => $module['name'],
                'description' => $module['description'] ?? null,
                'is_enabled' => true,
                'is_core' => $module['is_core'] ?? false,
                'created_by' => $firstUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('module_access')->whereIn('module_key', array_keys(config('modules')))->delete();
    }
};
