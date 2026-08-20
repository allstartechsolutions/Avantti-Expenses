<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The living work. A task outlives every meeting it is discussed in, so
     * there is one owner, one progress figure and one answer to "is this
     * done?" no matter how many minutes mention it.
     *
     * Parity rule, extended by one step: project_id AND job_site_id are both
     * nullable. Both null is a standalone task that belongs to no project —
     * somebody's own work, which never reaches a meeting agenda.
     *
     * Whether a task is "meeting-tracked" is NOT a column: it is true when
     * the task has at least one meeting_items row. origin_meeting_id only
     * records where it was first raised.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Display code (#142). Global rather than per project, because a
            // minute spanning several projects references tasks side by side.
            $table->unsignedInteger('number')->unique();

            $table->string('title');
            $table->longText('description')->nullable();

            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->cascadeOnDelete();

            // Two levels only: a sub-task cannot have children of its own.
            $table->unsignedBigInteger('parent_task_id')->nullable();

            // The only person who may declare the work ready.
            $table->foreignId('owner_id')->constrained('users');

            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', [
                'open', 'in_progress', 'blocked', 'ready', 'completed', 'cancelled',
            ])->default('open');

            // Manual 0-100, or the read-only roll-up of the sub-tasks when
            // the task has any.
            $table->unsignedTinyInteger('progress')->default(0);

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('blocked_reason')->nullable();

            // Where it was raised. origin_item_id gets its foreign key in the
            // meeting_items migration, which runs next.
            $table->foreignId('origin_meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->unsignedBigInteger('origin_item_id')->nullable();

            $table->timestamp('ready_at')->nullable();
            $table->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();

            // Set when the overdue mail goes out; cleared if the due date is
            // moved forward, so a rescheduled task can go overdue again.
            $table->timestamp('overdue_notified_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['job_site_id', 'status']);
            $table->index(['owner_id', 'status']);
            $table->index('status');
            $table->index('due_date');
            $table->index('parent_task_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('parent_task_id')->references('id')->on('tasks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
        });

        Schema::dropIfExists('tasks');
    }
};
