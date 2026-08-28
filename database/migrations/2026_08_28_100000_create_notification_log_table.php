<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mail log for everything that is not a task.
     *
     * `task_notifications` is task-shaped — it has a `task_id` foreign key —
     * and it is live, mailing real people today. Rather than reshape a table
     * on that path, new modules get a polymorphic log of the same design: a
     * row is written **before** the mail leaves, so a scheduled command that
     * runs twice in one day mails nobody twice, and "why did I not get an
     * e-mail?" is a query rather than a guess.
     *
     * `meta->window` is how a repeating reminder stays idempotent without a
     * stamp column per trigger: the window key is derived from the elapsed
     * time, so two runs on the same day resolve to the same key and the second
     * sends nothing. The weekly digest already works this way.
     *
     * The duplication with TaskNotifier's send-and-dedupe mechanics is
     * deliberate and is logged in docs/review-and-improvements.md as a
     * candidate for extraction once a third module wants the same thing.
     * Extracting it now would mean editing the live task mail path to save
     * sixty lines, which is a bad trade on a production system.
     */
    public function up(): void
    {
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();

            // Nullable: a digest-style mail belongs to a person and a window,
            // not to one record.
            $table->nullableMorphs('notifiable');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 40);
            $table->string('email');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // "Has this record's trigger already fired?" — the dedupe query.
            $table->index(['notifiable_type', 'notifiable_id', 'type'], 'notification_log_notifiable_type_idx');
            $table->index(['user_id', 'type']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
    }
};
