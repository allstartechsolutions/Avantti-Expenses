<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many days before its expiration date a document of this type
     * counts as "expiring soon" — 7, 15 or 30. Every existing type keeps the
     * 30 days the system has always used, so nothing moves on deploy.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('expiry_warning_days')->default(30)->after('requires_expiration');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('expiry_warning_days');
        });
    }
};
