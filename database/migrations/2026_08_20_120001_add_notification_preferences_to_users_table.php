<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A person's own opt-outs.
     *
     * Null means "whatever the install sends" — nobody has to be given a row
     * to receive the mail everyone else receives. A trigger a person has
     * switched off is listed here, and a trigger the install has switched off
     * beats anything in it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
