<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Register Meetings so an admin can switch it off from System Settings
     * like every other non-core module.
     */
    public function up(): void
    {
        $module = config('modules.meetings');

        if (! $module) {
            return;
        }

        DB::table('module_access')->insertOrIgnore([
            'module_key' => 'meetings',
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
        DB::table('module_access')->where('module_key', 'meetings')->delete();
    }
};
