<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('state');
            $table->decimal('rate', 5, 4);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('state');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
