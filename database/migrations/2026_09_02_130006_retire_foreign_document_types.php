<?php

use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A Brazilian install set up with the American document types keeps
     * them, but does not want W9 on every picker. This gives every seeded
     * row its key (2026_09_02_130004 only claimed the current country's
     * list, so an install's original rows were left without one) and then
     * retires the other country's types that hold no documents. Retiring is
     * what the Document Types screen does; it can be undone there.
     */
    public function up(): void
    {
        $seeder = new DocumentTypeSeeder;
        $seeder->run();
        $seeder->retireForeignUnused();
    }

    public function down(): void
    {
        // Retired rows stay retired; the screen reactivates them.
    }
};
