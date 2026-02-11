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
        Schema::create('estimate_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->enum('old_status', ['draft', 'sent', 'accepted', 'declined'])->nullable();
            $table->enum('new_status', ['draft', 'sent', 'accepted', 'declined']);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();

            $table->index('estimate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_status_histories');
    }
};
