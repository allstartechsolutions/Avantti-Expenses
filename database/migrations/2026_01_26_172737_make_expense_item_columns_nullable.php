<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make item-related columns nullable on expenses table since
     * item data has been moved to expense_items table.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('item_name')->nullable()->change();
            $table->enum('unit_type_used', ['purchase', 'usage', 'custom'])->nullable()->change();
            $table->decimal('quantity', 10, 2)->nullable()->change();
            $table->unsignedBigInteger('unit_price')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('item_name')->nullable(false)->default('')->change();
            $table->enum('unit_type_used', ['purchase', 'usage', 'custom'])->nullable(false)->default('custom')->change();
            $table->decimal('quantity', 10, 2)->nullable(false)->default(1)->change();
            $table->unsignedBigInteger('unit_price')->nullable(false)->default(0)->change();
        });
    }
};
