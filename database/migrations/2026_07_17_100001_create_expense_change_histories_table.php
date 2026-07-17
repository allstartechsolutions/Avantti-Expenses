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
        Schema::create('expense_change_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('expense_payment_id')->nullable()->constrained('expense_payments')->nullOnDelete();
            $table->string('action'); // marked_paid, unmarked_paid, marked_overdue, marked_pending, edited
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes')->nullable(); // field => ['old' => ..., 'new' => ...]
            $table->timestamps();

            $table->index('expense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_change_histories');
    }
};
