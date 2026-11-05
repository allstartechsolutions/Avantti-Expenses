<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The check a company expense was paid with — a number, or a short note about
 * the check. Only a company expense paid by check carries one; numbers typed
 * into `notes` before this column existed stay where they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('check_number')->nullable()->after('is_auto_payment');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('check_number');
        });
    }
};
