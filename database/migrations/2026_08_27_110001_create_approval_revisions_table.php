<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One round of an approval.
     *
     * The approval is the subject — "the porcelanato for the hall" — and this
     * is each time it was put forward. Revision 0 is the first submission;
     * a response of `revise_resubmit` opens revision 1, and so on.
     *
     * The response lives here rather than on the approval because a rejection
     * is a fact about *that submission*, not about the material. Keeping it on
     * the parent would mean the second attempt erased the record of the first,
     * which is exactly the history somebody needs three months later.
     *
     * `revision` is a string: BR practice numbers them 0,1,2 and US practice
     * often letters them 0,A,B. Neither is arithmetic.
     */
    public function up(): void
    {
        Schema::create('approval_revisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_id')->constrained()->cascadeOnDelete();
            $table->string('revision', 10);

            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('response_code_id')->nullable()
                ->constrained('collaboration_response_codes')->nullOnDelete();
            $table->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->unique(['approval_id', 'revision']);
            $table->index('responded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_revisions');
    }
};
