<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who is working this quotation round, and what they have been warned about.
     *
     * `assigned_to` is the **owner**: one person answerable for getting the
     * prices in. Collecting quotes is work several people can share, so the
     * round also gets collaborators — `quotation_assignees`, in the next
     * migration — which is where it differs from a requisition, whose single
     * `assigned_buyer_id` is an instruction addressed to one person.
     *
     * The two stamps mirror `tasks.overdue_notified_at` precisely: they mark
     * that a warning has gone out, so a daily command does not repeat it. Both
     * are **cleared when `responses_due_at` moves**, so a round whose deadline
     * is pushed can warn again later — a stamp that survived the change would
     * silently disarm the reminder for good.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('assigned_to')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable()->after('assigned_to');

            $table->timestamp('due_notified_at')->nullable()->after('assigned_at');
            $table->timestamp('overdue_notified_at')->nullable()->after('due_notified_at');

            // The queue lists "rounds I own, still open"; the scheduled
            // reminders scan by status and date.
            $table->index(['assigned_to', 'status'], 'quotations_assigned_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex('quotations_assigned_status_idx');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['assigned_at', 'due_notified_at', 'overdue_notified_at']);
        });
    }
};
