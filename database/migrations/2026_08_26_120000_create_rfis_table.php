<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A formal question put to the projetista or the owner, and the answer
     * tracked back to it.
     *
     * Scoped like every other record that can belong to either level: the
     * project is required, the job site optional. Numbering is per project,
     * not per site — an RFI is a conversation with the designer of the whole
     * job (docs/RFI-Submittals-modules.md, decision 1).
     *
     * `spec_section` and `drawing_ref` are the two markets' way of saying
     * where the question is about. A US RFI cites the specification section; a
     * BR one cites the prancha and its revision — "ARQ-04 rev.C". Both columns
     * exist on every install and the screen shows the one its country uses;
     * that is a rendering decision and never a branch in business logic.
     *
     * `cost_impact` and `schedule_impact` are flags, not money — which is why
     * this area is not `money => true`. What must be kept from an outside
     * designer is the *fact* that a question carries a cost, and that is the
     * `rfis.view_impact` grant.
     */
    public function up(): void
    {
        Schema::create('rfis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained('job_sites')->nullOnDelete();

            $table->string('number', 60);
            $table->string('subject');
            $table->text('question');

            $table->string('discipline', 60)->nullable();
            $table->string('spec_section', 60)->nullable();   // US
            $table->string('drawing_ref', 120)->nullable();   // BR

            $table->string('status', 20)->default('draft');
            $table->string('priority', 20)->default('normal');

            $table->foreignId('ball_in_court_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();

            $table->boolean('cost_impact')->default(false);
            $table->boolean('schedule_impact')->default(false);
            $table->unsignedSmallInteger('schedule_impact_days')->nullable();

            $table->text('answer')->nullable();
            $table->foreignId('answered_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();

            // The change order an answer led to. Never created automatically:
            // the screen offers the action and a person confirms it.
            $table->foreignId('change_order_id')->nullable()->constrained('change_orders')->nullOnDelete();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // A number is unique within its project, not globally — two
            // projects both having RFI-001 is correct.
            $table->unique(['project_id', 'number']);

            // The index list: this project's RFIs, newest first.
            $table->index(['project_id', 'status']);
            $table->index(['job_site_id', 'status']);
            // "What is with me, and what is late" — the two questions the
            // index is actually asked.
            $table->index(['ball_in_court_id', 'status']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfis');
    }
};
