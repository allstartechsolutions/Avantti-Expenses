<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert job_sites.job_amount
        DB::statement('UPDATE job_sites SET job_amount = job_amount * 100');
        Schema::table('job_sites', function (Blueprint $table) {
            $table->unsignedBigInteger('job_amount')->default(0)->change();
        });

        // Convert projects.initial_amount
        DB::statement('UPDATE projects SET initial_amount = initial_amount * 100');
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('initial_amount')->default(0)->change();
        });

        // Convert change_orders.amount
        DB::statement('UPDATE change_orders SET amount = amount * 100');
        Schema::table('change_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('amount')->change();
        });

        // Convert catalog_items.current_cost
        DB::statement('UPDATE catalog_items SET current_cost = current_cost * 100');
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->unsignedBigInteger('current_cost')->change();
        });

        // Convert catalog_item_price_history (old_cost, new_cost)
        DB::statement('UPDATE catalog_item_price_history SET old_cost = old_cost * 100, new_cost = new_cost * 100');
        Schema::table('catalog_item_price_history', function (Blueprint $table) {
            $table->unsignedBigInteger('old_cost')->change();
            $table->unsignedBigInteger('new_cost')->change();
        });

        // Convert expenses (unit_price, total_amount)
        DB::statement('UPDATE expenses SET unit_price = unit_price * 100, total_amount = total_amount * 100');
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price')->change();
            $table->unsignedBigInteger('total_amount')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert job_sites.job_amount
        Schema::table('job_sites', function (Blueprint $table) {
            $table->decimal('job_amount', 15, 2)->default(0)->change();
        });
        DB::statement('UPDATE job_sites SET job_amount = job_amount / 100');

        // Revert projects.initial_amount
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('initial_amount', 15, 2)->default(0)->change();
        });
        DB::statement('UPDATE projects SET initial_amount = initial_amount / 100');

        // Revert change_orders.amount
        Schema::table('change_orders', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });
        DB::statement('UPDATE change_orders SET amount = amount / 100');

        // Revert catalog_items.current_cost
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->decimal('current_cost', 10, 2)->change();
        });
        DB::statement('UPDATE catalog_items SET current_cost = current_cost / 100');

        // Revert catalog_item_price_history (old_cost, new_cost)
        Schema::table('catalog_item_price_history', function (Blueprint $table) {
            $table->decimal('old_cost', 10, 2)->change();
            $table->decimal('new_cost', 10, 2)->change();
        });
        DB::statement('UPDATE catalog_item_price_history SET old_cost = old_cost / 100, new_cost = new_cost / 100');

        // Revert expenses (unit_price, total_amount)
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->decimal('total_amount', 10, 2)->change();
        });
        DB::statement('UPDATE expenses SET unit_price = unit_price / 100, total_amount = total_amount / 100');
    }
};
