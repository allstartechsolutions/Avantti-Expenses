<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A person attached to one project or one job site, carrying its own
     * ability list. This is what an invitation creates and what the Team tab
     * shows.
     *
     * Resolution rules the columns exist to serve
     * (docs/permissions-module-plan.md §6):
     *
     *  - a membership on a job site OVERRIDES its project membership for that
     *    site — specific beats general;
     *  - a membership on a project CASCADES to every job site under it;
     *  - can_see_money off masks every monetary figure on that scope;
     *  - approval_limit caps the actions flagged `limited` in the catalogue.
     *
     * permission_template_id records what the membership was seeded from, so
     * the Team tab can say "Site Supervisor" or "Custom (based on Site
     * Supervisor)". It is a label, never consulted when resolving — the
     * abilities on the membership are the truth.
     */
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Project or JobSite. Written out rather than morphs() because the
            // composite index below already covers the type/id prefix.
            $table->string('scopeable_type');
            $table->unsignedBigInteger('scopeable_id');

            $table->foreignId('permission_template_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->string('title')->nullable();          // "Engenheiro residente"
            $table->boolean('can_see_money')->default(true);
            $table->unsignedBigInteger('approval_limit')->nullable();   // cents

            $table->enum('status', ['invited', 'active', 'suspended'])->default('active');

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scopeable_type', 'scopeable_id'], 'membership_user_scope_unique');
            $table->index(['scopeable_type', 'scopeable_id', 'status'], 'membership_scope_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
