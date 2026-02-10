<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rate_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->string('action');
            $table->string('field_changed')->nullable();
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rate_histories');
    }
};
