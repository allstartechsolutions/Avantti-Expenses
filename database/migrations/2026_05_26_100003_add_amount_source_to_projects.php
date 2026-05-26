<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 'manual' = user enters initial_amount directly (existing behavior)
            // 'from_jobsites' = contract value is sum(jobsites.job_amount), initial_amount ignored
            $table->string('amount_source', 20)->default('manual')->after('initial_amount');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('amount_source');
        });
    }
};
