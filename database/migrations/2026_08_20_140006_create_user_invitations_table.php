<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An invitation to somebody who has no login yet — staff or an outside
     * guest. The link carries a token; accepting it creates the user, sets
     * their password, and creates the memberships described in `payload`.
     *
     * Only the SHA-256 of the token is stored. A stolen database row cannot be
     * turned back into a working link, which matters because accepting one
     * creates an account.
     */
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();

            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('access_scope', ['company', 'assigned'])->default('assigned');
            $table->boolean('is_guest')->default(false);

            // The memberships to create on acceptance:
            // [{ scopeable_type, scopeable_id, permission_template_id,
            //    abilities: [...], can_see_money, approval_limit, title }]
            $table->json('payload')->nullable();

            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('send_count')->default(0);

            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
