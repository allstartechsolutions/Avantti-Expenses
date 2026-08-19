<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The quotations module works without a module_access row, because
     * ModuleAccess::isEnabled() defaults to true for an unknown key — but the
     * module settings screen lists rows, not config, so Quotations never
     * appeared there and an admin could not switch it off like every other
     * non-core module.
     */
    public function up(): void
    {
        $module = config('modules.quotations');

        if (! $module) {
            return;
        }

        DB::table('module_access')->insertOrIgnore([
            'module_key' => 'quotations',
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
        DB::table('module_access')->where('module_key', 'quotations')->delete();
    }
};
