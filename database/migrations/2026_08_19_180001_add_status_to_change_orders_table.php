<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change orders gain an approval state. Only an approved change order
     * revises the cost budget; the client contract value keeps counting every
     * change order regardless of status, exactly as it did before, so no live
     * project total moves when this ships.
     *
     * Existing rows take the 'approved' default — they were effective the
     * moment they were saved, which is what the system did until now.
     * `approved_at` / `approved_by` stay null on them: nobody ever approved
     * them, and pretending otherwise would be a false audit trail.
     */
    public function up(): void
    {
        Schema::table('change_orders', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])
                ->default('approved')
                ->after('requested_date');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->string('co_number', 50)->nullable()->after('title');

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('change_orders', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at', 'co_number']);
        });
    }
};
