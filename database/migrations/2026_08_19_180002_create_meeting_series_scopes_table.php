<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The projects and job sites a series normally covers. A new meeting
     * starts with these already on the agenda, which is what makes the open
     * items of those projects appear without anybody asking.
     *
     * Parity rule: project_id required, job_site_id nullable = the project
     * as a whole.
     */
    public function up(): void
    {
        Schema::create('meeting_series_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_series_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meeting_series_id', 'project_id', 'job_site_id'], 'meeting_series_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_series_scopes');
    }
};
