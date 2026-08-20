<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-user half of the permission model (docs/permissions-module-plan.md §2).
     *
     * access_scope 'company' is today's behaviour — the user sees every project.
     * 'assigned' confines them to the projects and job sites they hold a
     * membership on. Every existing user gets 'company', so this migration
     * changes nothing anyone can see; the switch is not offered in the UI until
     * every module has had its permission pass.
     *
     * is_guest marks somebody who is not staff — a client, an engineer, a vendor
     * with a login for one project. A guest is always confined.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('access_scope', ['company', 'assigned'])
                ->default('company')
                ->after('role_id');

            $table->boolean('is_guest')->default(false)->after('access_scope');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['access_scope', 'is_guest']);
        });
    }
};
