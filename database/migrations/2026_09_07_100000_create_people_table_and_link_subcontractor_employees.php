<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A person is the thread that ties one human's employee rows together as
     * they move from one subcontractor to the next. The employee row stays
     * what it was — one record per company, with whatever name and tax id
     * the person presented there — and gains a nullable pointer to the
     * person plus the audit of who linked it and why.
     *
     * Nothing is backfilled: a person record exists only once somebody has
     * decided two rows are the same human (see docs/people-module.md).
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });

        Schema::table('subcontractor_employees', function (Blueprint $table) {
            $table->string('tax_id', 50)->nullable()->after('email');
            $table->date('started_at')->nullable()->after('tax_id');
            $table->date('ended_at')->nullable()->after('started_at');

            $table->foreignId('person_id')->nullable()->after('subcontractor_id')
                ->constrained('people')->nullOnDelete();
            $table->foreignId('linked_by')->nullable()->after('notes')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable()->after('linked_by');
            $table->string('link_reason')->nullable()->after('linked_at');

            $table->index('tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('subcontractor_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
            $table->dropConstrainedForeignId('linked_by');
            $table->dropIndex(['tax_id']);
            $table->dropColumn(['tax_id', 'started_at', 'ended_at', 'linked_at', 'link_reason']);
        });

        Schema::dropIfExists('people');
    }
};
