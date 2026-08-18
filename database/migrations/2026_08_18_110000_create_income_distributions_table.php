<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splits a project-level income across the project's job sites.
     *
     * The income keeps its own amount; these rows only explain how it is
     * shared. Anything not distributed stays project-level, so a deposit can
     * be allocated as the work is assigned instead of all at once.
     *
     * The share is stored in cents. Percent is an input aid in the grid, not
     * a stored value — storing it would leave two sources of truth to
     * reconcile every time the income amount changes.
     */
    public function up(): void
    {
        Schema::create('income_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount'); // stored in cents
            $table->timestamps();

            // One share per job site per income: two rows for the same job
            // site would be the same statement said twice.
            $table->unique(['income_id', 'job_site_id']);
            $table->index('job_site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_distributions');
    }
};
