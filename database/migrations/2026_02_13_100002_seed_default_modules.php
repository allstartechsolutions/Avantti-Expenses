<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $firstUserId = DB::table('users')->orderBy('id')->value('id') ?? 1;
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
