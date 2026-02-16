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
        Schema::create('job_site_supervisor_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_site_supervisor_histories');
    }
};
