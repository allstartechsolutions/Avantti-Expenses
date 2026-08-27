<?php

use Database\Seeders\CollaborationResponseCodeSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Point the seeded response codes at the module's own language file.
     *
     * `label_key` is resolved with `__($code->label_key)`, so the value in the
     * column *is* the translation key — moving the module's strings out of the
     * global JSON therefore has to move the stored keys with them, or every
     * response on every approval starts rendering its raw key.
     *
     * The seeder matches on the natural key (project, market, document type,
     * canonical), so re-running it rewrites `label_key` in place rather than
     * inserting a second set.
     */
    public function up(): void
    {
        (new CollaborationResponseCodeSeeder)->run();
    }

    public function down(): void
    {
        // The seeder is the source of these rows; rolling the labels back would
        // mean re-seeding from an older revision of it, which `up()` above
        // would immediately undo. Nothing to do.
    }
};
