<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who keyed in this vendor's prices (docs/permissions-module.md, M8).
     *
     * `created_by` already records who added the vendor to the round, which is
     * a different act: inviting three vendors is administration, typing what
     * they quoted is where a number could be favoured. The self-award rule
     * needs the second, so it gets a column of its own.
     *
     * Nullable, and every existing proposal keeps a null — an unknown author
     * is never treated as the current user, so nothing already in the system
     * becomes un-awardable.
     */
    public function up(): void
    {
        Schema::table('quotation_vendors', function (Blueprint $table) {
            $table->foreignId('priced_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('priced_by');
        });
    }
};
