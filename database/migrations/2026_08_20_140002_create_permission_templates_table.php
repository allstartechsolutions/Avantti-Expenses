<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named, reusable ability list — "Site Supervisor", "Procurement",
     * "Client (read only)". Picked when somebody is invited to a project or a
     * job site; its abilities are copied onto the membership, which can then be
     * tweaked for that one person without touching the template.
     *
     * level says where a template may be used: 'project', 'job_site', or
     * 'global' for a role preset.
     *
     * is_system marks the ones shipped with the application. They can be edited
     * by the customer but are re-created if deleted, so an install always has
     * somewhere to start.
     */
    public function up(): void
    {
        Schema::create('permission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->nullable()->unique();   // system templates only
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('level', ['global', 'project', 'job_site']);

            // A template for outsiders: never offered when adding staff, and it
            // may not carry any company-wide ability.
            $table->boolean('is_guest')->default(false);
            $table->boolean('is_system')->default(false);

            // Defaults copied onto a membership created from this template.
            $table->boolean('can_see_money')->default(true);
            $table->unsignedBigInteger('approval_limit')->nullable();  // cents

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['level', 'is_guest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_templates');
    }
};
