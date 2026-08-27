<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who has to look at one revision, and in what order.
     *
     * `sequence` carries both shapes with one mechanism: equal values review
     * in **parallel**, ascending values review in **sequence**. The US chain
     * (GC → Architect → Engineer) is 1, 2, 3; the usual BR flow — straight to
     * the projetista — is a single row; and "both engineers, either order" is
     * two rows both at 1.
     *
     * `user_id` is not nullable: a reviewer is a person with a login, staff or
     * guest. That was decided when the guest system turned out to already
     * exist (docs/rfi-aprovacoes-discovery.md item 7) — there is no need for a
     * shadow contact record.
     */
    public function up(): void
    {
        Schema::create('approval_reviewers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('role', 40)->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            // One person reviews a revision once.
            $table->unique(['approval_revision_id', 'user_id'], 'appr_reviewer_unique');
            $table->index(['approval_revision_id', 'sequence'], 'appr_reviewer_seq_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_reviewers');
    }
};
