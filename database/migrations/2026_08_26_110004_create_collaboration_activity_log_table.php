<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what to a document, and who merely looked at it.
     *
     * Views are recorded, not only actions, and that is the point: "the
     * projetista was sent this on the 4th and opened it on the 5th" is the
     * sentence this table exists to be able to say. It matters evidentially in
     * Brazil, and it is far cheaper to record from the first day than to wish
     * for later.
     *
     * `user_id` is nullable so a row survives the person being deleted — an
     * audit trail that disappears when somebody leaves is not one. The name is
     * not denormalised here; `context` carries whatever the action needs.
     *
     * No `updated_at`: a log line is written once and never edited.
     */
    public function up(): void
    {
        Schema::create('collaboration_activity_log', function (Blueprint $table) {
            $table->id();

            $table->morphs('subject');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);                  // viewed | answered | closed…
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->nullable();

            // The document's own history, newest first.
            $table->index(['subject_type', 'subject_id', 'created_at'], 'ccal_subject_time_index');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_activity_log');
    }
};
