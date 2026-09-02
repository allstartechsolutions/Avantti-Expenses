<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A document type can be retired without touching the documents filed
     * under it. Production already holds documents against the seeded types,
     * so a type is never deleted: switching it off removes it from the upload
     * picker and nothing else. Every existing row stays active.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
