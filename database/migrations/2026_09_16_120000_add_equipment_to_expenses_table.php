<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Any expense — on a project or the company's — may be tagged to a piece
     * of equipment, and to the maintenance it paid for. The equipment page
     * sums them into its cost of ownership. Restrict on the equipment side:
     * an expense is a financial record, and deleting equipment with money
     * on it is refused (retire it instead); a deleted maintenance only
     * loses the finer tag. docs/equipment-module.md
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('expense_category_id')
                ->constrained('equipment')->restrictOnDelete();
            $table->foreignId('equipment_maintenance_id')->nullable()->after('equipment_id')
                ->constrained('equipment_maintenances')->nullOnDelete();

            $table->index(['equipment_id', 'expense_date'], 'expenses_equipment_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_equipment_date_index');
            $table->dropConstrainedForeignId('equipment_maintenance_id');
            $table->dropConstrainedForeignId('equipment_id');
        });
    }
};
