<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The coded answers a reviewer may give — A/Approved, C/Reapresentar, and
     * the rest. Rows, not a PHP enum, because the letters and the wording
     * differ between markets and a company may want its own.
     *
     * **Business logic reads `canonical` and nothing else.** `code` is what the
     * reviewer sees on the button and `label_key` is what it says; both are
     * presentation. `closes_cycle` is the one behavioural flag: it marks the
     * codes that end a revision, as against `revise_resubmit`, which opens the
     * next one.
     *
     * `market` seeds both sets ('us' and 'br'); which set is offered is decided
     * once from `config('app.country')`, because an installation serves one
     * company in one country (docs/rfi-aprovacoes-discovery.md item 1).
     *
     * `project_id` null is the global default. Project-scoped rows override it
     * — the column is here so a company that wants its own letters is not a
     * migration away, though nothing writes them yet.
     */
    public function up(): void
    {
        Schema::create('collaboration_response_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('market', 2);                   // us | br
            $table->string('document_type', 40);           // rfi | approval

            $table->string('code', 10);                    // A, B, C…
            $table->string('label_key');                   // translation key
            $table->string('canonical', 40);               // what the code MEANS
            $table->boolean('closes_cycle')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);

            $table->timestamps();

            // The lookup: every code offered for this document type, in order.
            $table->index(['document_type', 'market', 'project_id', 'sort'], 'ccrc_lookup_index');
            $table->index('canonical');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_response_codes');
    }
};
