<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The approval ceiling, away from a project (docs/permissions-module.md, F0).
 *
 * The ceiling has lived on a permission template and on a membership since the
 * engine was built, which means it binds inside a project and nowhere else —
 * so the same person could be stopped from releasing R$ 50.000 against a
 * contract and then release it from the payments dashboard with no ceiling at
 * all. That is P13 and P19.
 *
 * Two nullable columns, in cents, and null keeps its meaning: **no ceiling**.
 * So an install that upgrades and sets nothing behaves exactly as it does
 * today. The role carries the default for everybody who holds it, and the user
 * column overrides it for one person — the same shape as `access_scope`, which
 * is already nullable-on-user-falls-back-to-role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('approval_limit')->nullable()->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('approval_limit')->nullable()->after('access_scope');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('approval_limit');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('approval_limit');
        });
    }
};
