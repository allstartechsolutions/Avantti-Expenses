<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Only the projects they are added to", as a property of the role.
     *
     * The per-user switch (users.access_scope) already existed, but setting it
     * one person at a time is no way to run a company: a Site Supervisor role
     * should confine everybody who holds it. So the role carries the default
     * and the user column becomes an override — null means "follow the role".
     *
     * Every existing user is set to null by this migration and every role to
     * 'company', which is exactly what they all resolve to today. Nothing
     * changes until somebody sets a role to 'assigned', and even then nothing
     * is visible until the modules are converted.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('access_scope', ['company', 'assigned'])
                ->default('company')
                ->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('access_scope', ['company', 'assigned'])
                ->nullable()
                ->default(null)
                ->change();
        });

        // Hand everybody back to their role, so that changing the role takes
        // effect instead of being silently overridden by a per-user copy of
        // the same answer.
        DB::table('users')->where('access_scope', 'company')->update(['access_scope' => null]);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('access_scope')->update(['access_scope' => 'company']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('access_scope', ['company', 'assigned'])->default('company')->change();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('access_scope');
        });
    }
};
