<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('cardpointe_profile_id');
            $table->string('acctid');
            $table->string('card_last_four', 4);
            $table->string('card_brand')->nullable();
            $table->string('expiry', 4)->nullable();
            $table->string('token');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['client_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_methods');
    }
};
