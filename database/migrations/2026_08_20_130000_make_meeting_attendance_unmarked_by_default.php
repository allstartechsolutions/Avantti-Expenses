<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance starts blank.
     *
     * It used to default to "present", seeded from the series, which meant an
     * untouched register recorded people who were never in the room. A minute
     * is a record; it should say "not recorded" rather than assert something
     * nobody checked.
     *
     * Existing rows are left exactly as they are — they were marked by hand or
     * were part of an already published minute either way.
     */
    public function up(): void
    {
        Schema::table('meeting_attendees', function (Blueprint $table) {
            $table->enum('attendance', ['present', 'absent', 'excused'])->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_attendees', function (Blueprint $table) {
            $table->enum('attendance', ['present', 'absent', 'excused'])->default('present')->change();
        });
    }
};
