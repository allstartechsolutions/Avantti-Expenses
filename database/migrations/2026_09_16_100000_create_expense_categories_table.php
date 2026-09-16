<?php

use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Company (general) expenses — rent, utilities, insurance, fuel — belong
     * to no project, so they have no budget and no cost code. They are
     * categorised by this company-level list instead, and each category
     * carries a 4-digit account code so the customer's accounting software
     * can match what is exported (docs/company-expenses.md).
     *
     * `expenses.expense_category_id` is added here, nullable, so the Settings
     * screen can count what is filed under a category from day one; the
     * column is only ever set on a company expense (project expenses keep
     * their cost code on the line). Making `project_id` nullable is the next
     * migration's business.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            // Stable seed key (`overhead.rent`); a category made on the
            // screen has none. The seeder finds its rows by this, never by
            // the name, which the screen can change.
            $table->string('key', 50)->nullable()->unique();
            $table->string('name', 100);
            $table->char('account_code', 4)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            // Restrict, never cascade: a category with expenses is retired
            // on the screen, and the screen refuses to delete it.
            $table->foreignId('expense_category_id')
                ->nullable()
                ->after('job_site_id')
                ->constrained('expense_categories')
                ->restrictOnDelete();
        });

        (new ExpenseCategorySeeder)->run();
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_category_id');
        });

        Schema::dropIfExists('expense_categories');
    }
};
