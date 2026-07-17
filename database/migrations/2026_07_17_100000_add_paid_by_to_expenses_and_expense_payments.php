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
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('paid_by')->nullable()->after('paid_date')->constrained('users')->nullOnDelete();
        });

        Schema::table('expense_payments', function (Blueprint $table) {
            $table->foreignId('paid_by')->nullable()->after('paid_date')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
        });

        Schema::table('expense_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
        });
    }
};
