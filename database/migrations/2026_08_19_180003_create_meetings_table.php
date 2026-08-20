<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One meeting, and once published, one minute (ata de reunião).
     *
     * A meeting is company-level: it spans as many projects as were on the
     * agenda. The project scope lives on the items, not here.
     *
     * A published meeting is a frozen record. Corrections are logged in
     * meeting_revisions rather than applied silently.
     */
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_series_id')->nullable()->constrained()->nullOnDelete();

            // OBRA-2026-014 — series code, year, per-series-per-year sequence.
            // Ad-hoc meetings use the ATA prefix.
            $table->string('number', 40)->unique();
            $table->string('title');

            $table->date('meeting_date');
            $table->time('started_at')->nullable();
            $table->time('ended_at')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();

            // The chair confirms the completions the owners declared ready.
            $table->foreignId('chair_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('secretary_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', ['draft', 'published', 'cancelled'])->default('draft');

            // Set from the series when the meeting is created; the source of
            // the carry-forward.
            $table->unsignedBigInteger('previous_meeting_id')->nullable();
            $table->unsignedBigInteger('next_meeting_id')->nullable();
            $table->date('next_meeting_date')->nullable();

            $table->longText('summary')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();

            // The rendered minute, once filed into the project repository.
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['meeting_series_id', 'meeting_date']);
            $table->index('status');
            $table->index('meeting_date');
        });

        // Self references, added once the table exists.
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreign('previous_meeting_id')->references('id')->on('meetings')->nullOnDelete();
            $table->foreign('next_meeting_id')->references('id')->on('meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['previous_meeting_id']);
            $table->dropForeign(['next_meeting_id']);
        });

        Schema::dropIfExists('meetings');
    }
};
