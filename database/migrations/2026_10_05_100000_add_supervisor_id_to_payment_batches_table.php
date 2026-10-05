<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The job-site supervisor a batch was built for, saved beside the
     * project manager like the other contract filters.
     */
    public function up(): void
    {
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('project_manager_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }
};
