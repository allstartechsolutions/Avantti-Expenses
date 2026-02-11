<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_payment_methods', function (Blueprint $table) {
            $table->string('card_name')->nullable()->after('card_brand');
        });
    }

    public function down(): void
    {
        Schema::table('client_payment_methods', function (Blueprint $table) {
            $table->dropColumn('card_name');
        });
    }
};
