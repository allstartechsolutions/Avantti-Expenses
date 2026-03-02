<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('notes')->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('subcontractor_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->after('subcontractor_id')->constrained('users')->nullOnDelete();
            $table->string('contract_status_filter')->nullable()->after('project_manager_id');
            $table->boolean('show_zero_balance')->default(false)->after('contract_status_filter');
        });
    }

    public function down(): void
    {
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['subcontractor_id']);
            $table->dropForeign(['project_manager_id']);
            $table->dropColumn([
                'client_id', 'project_id', 'subcontractor_id',
                'project_manager_id', 'contract_status_filter', 'show_zero_balance',
            ]);
        });
    }
};
