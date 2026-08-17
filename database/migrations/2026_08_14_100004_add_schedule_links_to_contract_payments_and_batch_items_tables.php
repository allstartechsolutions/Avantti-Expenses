<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->foreignId('contract_schedule_item_id')->nullable()->after('contract_id')->constrained()->nullOnDelete();
            $table->foreignId('contract_measurement_id')->nullable()->after('contract_schedule_item_id')->constrained()->nullOnDelete();
            $table->boolean('is_retention_release')->default(false)->after('contract_measurement_id');
        });

        Schema::table('payment_batch_items', function (Blueprint $table) {
            $table->foreignId('contract_schedule_item_id')->nullable()->after('contract_id')->constrained()->nullOnDelete();
            $table->foreignId('contract_measurement_id')->nullable()->after('contract_schedule_item_id')->constrained()->nullOnDelete();
            $table->boolean('is_retention_release')->default(false)->after('contract_measurement_id');
        });
    }

    public function down(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_schedule_item_id');
            $table->dropConstrainedForeignId('contract_measurement_id');
            $table->dropColumn('is_retention_release');
        });

        Schema::table('payment_batch_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_schedule_item_id');
            $table->dropConstrainedForeignId('contract_measurement_id');
            $table->dropColumn('is_retention_release');
        });
    }
};
