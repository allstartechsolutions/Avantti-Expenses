<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recurring meeting — "Weekly Site Meeting", "Directors Meeting".
     *
     * The series is what makes carry-forward meaningful: the open items of a
     * weekly site meeting must not land on the agenda of the directors
     * meeting, so "the previous meeting" is always read within one series.
     *
     * See docs/meetings-module-plan.md.
     */
    public function up(): void
    {
        Schema::create('meeting_series', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();

            // Display and next-date suggestion only. Nothing is scheduled
            // automatically — the follow-up meeting is created by a person.
            $table->enum('cadence', ['weekly', 'biweekly', 'monthly', 'quarterly', 'ad_hoc'])
                ->default('weekly');

            $table->string('default_location')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_series');
    }
};
