<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login activity (docs/login-activity.md): one row per sign-in, failed attempt,
 * logout, lockout and two-factor step, kept 90 days. Plus the user's last
 * login on the users row, so the Users list needs no subquery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('event', 32);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('platform', 64)->nullable();
            $table->string('device', 16)->nullable();
            $table->string('fingerprint', 40)->nullable();
            $table->boolean('via_remember')->default(false);
            $table->boolean('is_new_device')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'last_login_ip']);
        });

        Schema::dropIfExists('login_events');
    }
};
