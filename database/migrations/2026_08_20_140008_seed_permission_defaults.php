<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Fills the tables the previous migrations created: the system templates,
     * the ability lists of the manager and employee roles, and a membership
     * for every project manager and job-site supervisor.
     *
     * Done as a migration rather than a seeder because production is deployed
     * with `php artisan migrate` alone, and the backfill has to happen there.
     * The same logic is available as `php artisan permissions:sync`, and both
     * are safe to run again — nothing existing is overwritten.
     *
     * Nothing here changes what anybody sees: every area in
     * config/permissions.php is still `swept => false`, so the resolver keeps
     * using today's role checks until each module has had its pass.
     */
    public function up(): void
    {
        app(PermissionSeeder::class)->run();
    }

    public function down(): void
    {
        // The tables are dropped by the migrations that created them.
    }
};
