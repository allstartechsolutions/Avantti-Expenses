<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A vendor can be switched off without being deleted: a supplier the
     * company stopped buying from, a subcontractor it will not hire again.
     * An inactive vendor keeps every record that points at it and stays on
     * the lists with an "Inactive" badge, but is no longer offered when a
     * new expense, purchase order, contract, quotation or catalog item
     * picks a vendor. Who switched it off, and when, is kept for the
     * detail page. docs/vendor-unification.md
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_subcontractor')->index();
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropColumn(['deactivated_at', 'is_active']);
        });
    }
};
