<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who was invited and who actually turned up. Seeded from the series
     * members, then corrected on the day.
     *
     * External people (clients, engineers, vendors) have no user_id — they
     * are a name, a company and an e-mail that receives the published minute.
     */
    public function up(): void
    {
        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('email')->nullable();

            $table->enum('role', ['chair', 'secretary', 'participant'])->default('participant');
            $table->enum('attendance', ['present', 'absent', 'excused'])->default('present');
            $table->text('notes')->nullable();

            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index('meeting_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendees');
    }
};
