<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-person company-wide permissions (docs/permissions-module.md, F0).
 *
 * Until now a company-wide ability could only come from a role, so giving one
 * person one extra thing — or taking one thing away from them — meant inventing
 * a role for them. This is the record that fixes it, and it closes P6, P19 and
 * P34 in docs/review-and-improvements.md.
 *
 * One row per person per ability they differ from their role on:
 *
 *   granted = true   always allowed, whatever the role says
 *   granted = false  never allowed, whatever the role says
 *   no row           follow the role, which is the normal case
 *
 * Nothing is written here by a migration or a seed. An empty table means every
 * user answers exactly as they did before, which is the point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ability', 100);
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'ability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_abilities');
    }
};
