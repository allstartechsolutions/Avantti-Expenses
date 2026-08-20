<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The people a series normally invites. Copied onto every new meeting as
     * its attendance list, then corrected on the day.
     *
     * user_id is nullable so external participants — a client, an engineer,
     * a vendor with no login — can be standing members too.
     */
    public function up(): void
    {
        Schema::create('meeting_series_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_series_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('email')->nullable();

            $table->enum('role', ['chair', 'secretary', 'participant'])->default('participant');
            $table->timestamps();

            $table->index('meeting_series_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_series_members');
    }
};
