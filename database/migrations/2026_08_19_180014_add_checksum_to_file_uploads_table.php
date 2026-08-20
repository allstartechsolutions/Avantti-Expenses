<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shared completeUpload() records the object's ETag as a checksum, the
     * way document_versions already does. file_uploads needs the column to
     * take it.
     */
    public function up(): void
    {
        Schema::table('file_uploads', function (Blueprint $table) {
            $table->string('checksum')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('file_uploads', function (Blueprint $table) {
            $table->dropColumn('checksum');
        });
    }
};
