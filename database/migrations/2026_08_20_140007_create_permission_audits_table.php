<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every grant, revoke and template change, so an admin can answer "who gave
     * him that, and when?".
     *
     * Deliberately not tied to memberships by foreign key: removing somebody
     * from a project must not erase the record that they were once on it.
     */
    public function up(): void
    {
        Schema::create('permission_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->nullableMorphs('scopeable');            // the project / job site, if any
            $table->string('subject_type', 40);             // role | template | membership | invitation | user
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('action', 40);                   // granted | revoked | created | updated | ...
            $table->string('summary')->nullable();          // one line for the timeline
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index('subject_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_audits');
    }
};
