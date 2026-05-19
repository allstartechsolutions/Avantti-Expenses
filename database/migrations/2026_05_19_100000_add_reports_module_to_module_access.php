<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $firstUserId = DB::table('users')->orderBy('id')->value('id') ?? 1;
        $now = now();

        $module = config('modules.reports');

        if (!$module) {
            return;
        }

        DB::table('module_access')->insertOrIgnore([
            'module_key' => 'reports',
            'module_name' => $module['name'],
            'description' => $module['description'] ?? null,
            'is_enabled' => true,
            'is_core' => $module['is_core'] ?? false,
            'created_by' => $firstUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('module_access')->where('module_key', 'reports')->delete();
    }
};
