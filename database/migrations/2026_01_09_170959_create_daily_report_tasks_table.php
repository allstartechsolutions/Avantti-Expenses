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
        Schema::create('daily_report_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->text('description');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['daily_report_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_report_tasks');
    }
};
