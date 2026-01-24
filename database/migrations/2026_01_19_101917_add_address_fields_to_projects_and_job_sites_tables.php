<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('address_2', 255)->nullable()->after('street');
            $table->string('neighborhood', 255)->nullable()->after('postal_code');
            $table->string('country', 2)->default('US')->after('neighborhood');
        });

        Schema::table('job_sites', function (Blueprint $table) {
            $table->string('address_2', 255)->nullable()->after('street');
            $table->string('neighborhood', 255)->nullable()->after('postal_code');
            $table->string('country', 2)->default('US')->after('neighborhood');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['address_2', 'neighborhood', 'country']);
        });

        Schema::table('job_sites', function (Blueprint $table) {
            $table->dropColumn(['address_2', 'neighborhood', 'country']);
        });
    }
};
