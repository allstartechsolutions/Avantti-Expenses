<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hand the two new vendor-document abilities to whoever may edit vendors.
     *
     * `vendors.renew_documents` (upload and renew) and
     * `vendors.archive_documents` are new *actions on an area every role has
     * already been offered*, so `PermissionSeeder::grantAbilitiesOfNewAreas()`
     * will not touch them — it only hands over whole areas a role has never
     * seen. Without this, every role would wake up unable to upload a
     * document, which is exactly the silent narrowing the permission rules
     * forbid.
     *
     * Reproduce first: uploading and deleting a vendor document had no guard
     * of their own before this — the page's `vendors.view` was the only
     * check. The intended level was always "may edit the vendor", so that is
     * who receives the grant: every role, and every per-person override,
     * holding `vendors.edit`. Administrators bypass the ability tables and
     * need nothing here. After this runs the two abilities are revocable on
     * the access screens like any other.
     */
    private const ABILITIES = ['vendors.renew_documents', 'vendors.archive_documents'];

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('role_abilities')) {
            $roleIds = DB::table('role_abilities')
                ->where('ability', 'vendors.edit')
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                $held = DB::table('role_abilities')
                    ->where('role_id', $roleId)
                    ->whereIn('ability', self::ABILITIES)
                    ->pluck('ability')
                    ->all();

                foreach (array_diff(self::ABILITIES, $held) as $ability) {
                    DB::table('role_abilities')->insert([
                        'role_id' => $roleId,
                        'ability' => $ability,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (Schema::hasTable('user_abilities')) {
            $userIds = DB::table('user_abilities')
                ->where('ability', 'vendors.edit')
                ->where('granted', true)
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $held = DB::table('user_abilities')
                    ->where('user_id', $userId)
                    ->whereIn('ability', self::ABILITIES)
                    ->pluck('ability')
                    ->all();

                foreach (array_diff(self::ABILITIES, $held) as $ability) {
                    DB::table('user_abilities')->insert([
                        'user_id' => $userId,
                        'ability' => $ability,
                        'granted' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['role_abilities', 'user_abilities'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('ability', self::ABILITIES)->delete();
            }
        }
    }
};
