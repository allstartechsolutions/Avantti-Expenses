<?php

use Database\Seeders\CatalogCategorySeeder;
use Database\Seeders\DefaultSupplierSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        (new RoleSeeder)->run();
        (new CatalogCategorySeeder)->run();
        (new DocumentTypeSeeder)->run();

        // Default supplier needs a valid user FK; if no users yet, the setup
        // wizard runs this seeder right after creating the first admin.
        if (DB::table('users')->exists()) {
            (new DefaultSupplierSeeder)->run();
        }
    }

    public function down(): void
    {
        // No-op: removing seeded rows could discard customer data referencing them.
    }
};
