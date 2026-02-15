<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcontractor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_number')->unique();
            $table->enum('status', ['active', 'completed', 'partially_paid', 'paid', 'cancelled'])->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->text('notes')->nullable();
            $table->string('contract_file_path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('project_id');
            $table->index('job_site_id');
            $table->index('status');
            $table->index('contract_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
