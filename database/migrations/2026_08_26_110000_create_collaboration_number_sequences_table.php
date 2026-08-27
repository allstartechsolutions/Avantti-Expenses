<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per project per document type, and the only place a document
     * number comes from.
     *
     * Every other module in this codebase numbers its records by reading the
     * highest one it can find and adding one — `Meeting::nextNumber()` is the
     * clearest example. That is not gapless: delete the last record and the
     * next one issued reuses its number, which is exactly what a document
     * somebody has already been sent must never do. Here the counter is a
     * column, locked for the length of a transaction, and it only ever goes up.
     *
     * `template` renders the number: `{prefix}-{discipline}-{seq:000}` gives
     * SI-ARQ-014. `start_value` lets a project migrating off a spreadsheet
     * start its RFIs at 47. Both stay editable until the first number is
     * issued, and `locked` is what says that has happened — renumbering a
     * document that is already in somebody's inbox is not an edit, it is a
     * different document.
     *
     * Built generic on purpose (`document_type` is a plain string) so purchase
     * orders and change orders can move onto it later. Only 'rfi' and
     * 'approval' use it today; nothing existing is being refactored here.
     */
    public function up(): void
    {
        Schema::create('collaboration_number_sequences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);            // rfi | approval

            $table->string('template', 100);
            $table->unsignedInteger('start_value')->default(1);
            $table->unsignedInteger('current_value')->default(0);
            $table->boolean('locked')->default(false);

            $table->timestamps();

            // One counter per project per type. This is also what makes the
            // read-modify-write safe: the row is found by this key, locked,
            // and released at commit.
            $table->unique(['project_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_number_sequences');
    }
};
