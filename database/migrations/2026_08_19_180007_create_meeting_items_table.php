<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One line of the agenda, and the join between a meeting and a task:
     * "at this meeting, about this project, we discussed this task, and this
     * is what was said".
     *
     * The same task appears in June's minute and July's minute as two items,
     * each with its own discussion — which is why carrying an item forward
     * never copies the work.
     *
     * The displayed number (1, 2, 2.1) is computed from position and the
     * parent chain, never stored: reordering must not rewrite every row.
     */
    public function up(): void
    {
        Schema::create('meeting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->cascadeOnDelete();

            $table->enum('type', ['information', 'decision', 'action'])->default('action');
            $table->string('title');
            $table->longText('discussion')->nullable();
            $table->longText('decision')->nullable();

            // Null on delete, not cascade: a published minute must survive the
            // deletion of the task it discussed. The item keeps its own title
            // and text, so the record stays readable.
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();

            $table->unsignedBigInteger('carried_from_item_id')->nullable();

            // The task's status, progress and due date as at publication, so
            // the minute keeps saying 60% when the task has moved on to 90%.
            $table->json('status_at_meeting')->nullable();

            // An item put on the agenda but never reached rolls over instead
            // of being recorded as discussed.
            $table->boolean('discussed')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['meeting_id', 'position']);
            $table->index('task_id');
            $table->index(['project_id', 'job_site_id']);
        });

        Schema::table('meeting_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('meeting_items')->cascadeOnDelete();
            $table->foreign('carried_from_item_id')->references('id')->on('meeting_items')->nullOnDelete();
        });

        // Close the loop from the task back to the item that raised it.
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('origin_item_id')->references('id')->on('meeting_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['origin_item_id']);
        });

        Schema::table('meeting_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['carried_from_item_id']);
        });

        Schema::dropIfExists('meeting_items');
    }
};
