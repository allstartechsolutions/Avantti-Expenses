<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the company expects to pay a subcontractor for a lot before any
 * contract exists (client request 22, Nov 2026). Information only: it is
 * shown on the overview and never enters the profit. Plain currency, not cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_sites', function (Blueprint $table) {
            $table->decimal('estimated_cost', 15, 2)->nullable()->after('job_amount');
        });
    }

    public function down(): void
    {
        Schema::table('job_sites', function (Blueprint $table) {
            $table->dropColumn('estimated_cost');
        });
    }
};
