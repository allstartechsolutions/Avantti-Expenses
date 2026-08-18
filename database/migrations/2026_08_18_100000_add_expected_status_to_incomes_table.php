<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Income can now be money already received or money still expected, so
     * receivables no longer depend on an invoice existing. Existing rows
     * default to 'received', which is exactly what they are.
     */
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->enum('status', ['received', 'expected'])->default('received')->after('amount');
            $table->date('due_date')->nullable()->after('income_date');

            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['due_date']);
            $table->dropColumn(['status', 'due_date']);
        });
    }
};
