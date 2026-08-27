<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Essential seeders for production
        $this->call([
            RoleSeeder::class,
            CatalogCategorySeeder::class,
            // Reference data for the collaboration module. Also applied by its
            // own migration, so an existing install gets it on deploy without
            // anybody remembering to seed.
            CollaborationResponseCodeSeeder::class,
        ]);

        // Create default admin user
        $adminRole = Role::where('name', 'admin')->first();

        User::firstOrCreate(
            ['email' => 'jr@allstartechsolutions.com'],
            [
                'name' => 'Joao Andrade',
                'password' => '2402819828aA@',
                'email_verified_at' => now(),
                'role_id' => $adminRole?->id,
            ]
        );

        // Runs last: the permission templates and role abilities are seeded
        // from config/permissions.php, and the backfill needs the roles and
        // any projects to exist first. Same code as `php artisan permissions:sync`.
        $this->call(PermissionSeeder::class);
    }
}
