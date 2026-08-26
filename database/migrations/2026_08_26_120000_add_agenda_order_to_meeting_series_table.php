<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How agendas built from this series are ordered.
     *
     * Carrying items forward used to sort them by due date alone, which threw
     * away the order the chair had dragged the previous agenda into and mixed
     * the projects together. Agendas now group by project / job site and keep
     * the previous meeting's order inside each group.
     *
     * Some chairs would rather see the late work at the top of each group.
     * That is a property of the meeting rather than of the person reading it —
     * a per-user preference would mean the chair, the secretary and the ata
     * all read differently — so it lives on the series and sets the default
     * for every agenda built from it.
     *
     * See docs/meetings-agenda-order-plan.md §2.4.
     */
    public function up(): void
    {
        Schema::table('meeting_series', function (Blueprint $table) {
            $table->enum('agenda_order', ['last_meeting', 'overdue_first'])
                ->default('last_meeting')
                ->after('cadence');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_series', function (Blueprint $table) {
            $table->dropColumn('agenda_order');
        });
    }
};
