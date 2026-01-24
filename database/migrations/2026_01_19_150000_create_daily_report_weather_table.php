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
        Schema::create('daily_report_weather', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();

            // Temperature (stored in Fahrenheit)
            $table->decimal('temp_low', 5, 2)->nullable();
            $table->decimal('temp_high', 5, 2)->nullable();
            $table->decimal('temp_avg', 5, 2)->nullable();

            // Precipitation (stored in inches)
            $table->decimal('precip_midnight', 6, 2)->nullable()->comment('Precipitation since midnight');
            $table->decimal('precip_2_days', 6, 2)->nullable()->comment('Precipitation last 2 days');
            $table->decimal('precip_3_days', 6, 2)->nullable()->comment('Precipitation last 3 days');

            // Humidity (percentages)
            $table->unsignedTinyInteger('humidity_low')->nullable();
            $table->unsignedTinyInteger('humidity_avg')->nullable();
            $table->unsignedTinyInteger('humidity_high')->nullable();
            $table->decimal('dew_point', 5, 2)->nullable();

            // Wind (stored in mph)
            $table->decimal('wind_avg', 5, 2)->nullable();
            $table->decimal('wind_max', 5, 2)->nullable();
            $table->decimal('wind_gust', 5, 2)->nullable();

            // Daily snapshots (JSON array for 6 time periods)
            $table->json('snapshots')->nullable();

            // General conditions
            $table->string('conditions')->nullable();
            $table->string('icon')->nullable();

            // Metadata
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            // Each daily report should only have one weather record
            $table->unique('daily_report_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_report_weather');
    }
};
