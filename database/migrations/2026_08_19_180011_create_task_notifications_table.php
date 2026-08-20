<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mail log. Every task e-mail is recorded before it is considered
     * sent, so a scheduled command that runs twice in one day mails nobody
     * twice, and "why did I not get an e-mail?" is a query rather than a
     * guess.
     */
    public function up(): void
    {
        Schema::create('task_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // created, closed, overdue, weekly_digest
            $table->string('type', 30);
            $table->string('email');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'user_id', 'type']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_notifications');
    }
};
