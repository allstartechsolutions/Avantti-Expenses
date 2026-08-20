<?php

use App\Services\AbilityCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which areas a role has already been offered.
     *
     * `permissions:sync` gives a role the abilities of an area that did not
     * exist when it was seeded. It used to decide that by asking whether the
     * role held *anything* from the area — which is wrong, and was caught in
     * use: an administrator who deliberately revoked every Expenses ability
     * from a role had them all handed back on the next deploy.
     *
     * The record of what has been offered has to be kept explicitly. Every
     * existing role is backfilled with the whole catalogue as it stands, so
     * nothing already on offer can ever be re-granted; only genuinely new
     * areas reach anybody.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('seeded_areas')->nullable()->after('access_scope');
        });

        $areas = json_encode(array_keys(AbilityCatalog::areas()));

        DB::table('roles')->update(['seeded_areas' => $areas]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('seeded_areas');
        });
    }
};
