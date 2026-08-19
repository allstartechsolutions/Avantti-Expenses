<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->enum('old_status', [
                'draft', 'sent', 'comparing', 'negotiating', 'awarded', 'converted', 'cancelled',
            ])->nullable();
            $table->enum('new_status', [
                'draft', 'sent', 'comparing', 'negotiating', 'awarded', 'converted', 'cancelled',
            ]);
            $table->foreignId('changed_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_status_histories');
    }
};
