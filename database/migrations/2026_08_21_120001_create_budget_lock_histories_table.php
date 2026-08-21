<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every lock and unlock, kept.
     *
     * `budgets.locked_at` / `locked_by` only describe the state right now; a
     * budget that was frozen, reopened and frozen again would otherwise leave
     * no trace of the middle. A baseline that can be reopened is only worth
     * having if reopening is on the record.
     */
    public function up(): void
    {
        Schema::create('budget_lock_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);                 // locked | unlocked
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['budget_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lock_histories');
    }
};
