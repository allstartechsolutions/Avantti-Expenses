<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Budget locking (docs/permissions-module.md, M6).
     *
     * A locked budget's *plan* stops moving — its cost codes and their planned
     * amounts are fixed, and the budget cannot be deleted. Everything that
     * reports against it carries on: expenses, purchase orders and change
     * orders still code to it and the variance keeps updating.
     *
     * Both columns are nullable and every existing budget is unlocked, so this
     * changes nothing anyone can see until somebody holding `budget.lock`
     * throws the switch.
     */
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('notes');

            $table->foreignId('locked_by')
                ->nullable()
                ->after('locked_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
        });
    }
};
