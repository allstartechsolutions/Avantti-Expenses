<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_orders', function (Blueprint $table) {
            // Allow negative amounts for deductive change orders. Values are
            // stored as cents (set by the 2026_01_06 monetary conversion).
            $table->bigInteger('amount')->change();
        });
    }

    public function down(): void
    {
        Schema::table('change_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('amount')->change();
        });
    }
};
