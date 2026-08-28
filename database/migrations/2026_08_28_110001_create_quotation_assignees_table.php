<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The people working a quotation round besides its owner. The owner is
     * always an implicit collaborator and is not duplicated here.
     *
     * Same shape and same rule as `task_assignees`, deliberately: two tables
     * that mean the same thing should not be read two different ways.
     *
     * **A row here grants nothing.** Being added to a round is a work list,
     * not a permission: a collaborator without `quotations.edit` sees the
     * round and cannot price it, and that is correct. Softening a guard
     * because "they were assigned" is precisely the hole the permissions sweep
     * spent a week closing.
     */
    public function up(): void
    {
        Schema::create('quotation_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['quotation_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_assignees');
    }
};
