<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A subcontractor's employee can be switched off without being deleted
     * or given an end date: kept on the vendor page with an "Inactive" chip,
     * still named on every contract that already points at them, but no
     * longer offered as the contact of a new contract. Independent of the
     * employment period (`started_at` / `ended_at`). Every existing row
     * stays active.
     */
    public function up(): void
    {
        Schema::table('subcontractor_employees', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('ended_at')->index();
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subcontractor_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropColumn(['is_active', 'deactivated_at']);
        });
    }
};
