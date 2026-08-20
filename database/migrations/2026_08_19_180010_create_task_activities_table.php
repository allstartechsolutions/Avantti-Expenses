<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every change a task went through, in the same spirit as the
     * *_status_histories tables elsewhere in the app. "Who moved my due
     * date?" has to be answerable from the database.
     */
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // created, status_changed, progress_changed, due_date_changed,
            // owner_changed, assignee_added, assignee_removed, note_added,
            // file_added, discussed, reopened
            $table->string('action', 40);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('notes')->nullable();

            // Set when the change happened during a meeting.
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['task_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
