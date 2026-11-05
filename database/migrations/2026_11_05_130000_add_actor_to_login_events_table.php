<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who ended a session for somebody else (docs/login-activity.md, stage 4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_events', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('login_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_id');
        });
    }
};
