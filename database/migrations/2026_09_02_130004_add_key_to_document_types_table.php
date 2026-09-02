<?php

use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stable key for the seeded document types.
     *
     * The Document Types screen lets an administrator rename a type, and the
     * seeder used to find its rows by name — so a rename followed by any
     * later seed (a migration adding a type, a `db:seed`) would have created
     * the old row again beside the renamed one. Seeded rows now carry a key
     * (`us.w9`, `br.cnd_federal`) that nothing on screen can change; a type
     * created on the screen has none. The seeder backfills the key onto rows
     * that already exist under the seeded name, then adds what is missing for
     * this install's country.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->string('key', 40)->nullable()->unique()->after('id');
        });

        (new DocumentTypeSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }
};
