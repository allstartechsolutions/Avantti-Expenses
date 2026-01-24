<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_report_weather_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();

            // Time of observation
            $table->time('observed_at');

            // Weather delay indicator
            $table->boolean('weather_delay')->default(false);

            // Conditions (stored as enum values)
            $table->string('sky_condition', 50)->nullable();
            $table->decimal('temperature', 5, 2)->nullable()->comment('Stored in Fahrenheit');
            $table->string('wind_condition', 50)->nullable();
            $table->string('precipitation', 50)->nullable();

            // Additional notes
            $table->text('notes')->nullable();

            // Display order
            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            // Index for ordering
            $table->index(['daily_report_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_report_weather_observations');
    }
};
