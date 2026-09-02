<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor documents join the shared upload path.
     *
     * A document filed from now on is a `file_uploads` row — the same
     * presigned, direct-to-bucket transport tasks and RFIs use, stored under
     * `vendors/{id}/…` on whichever disk the install has — and this column
     * points at it. `file_path` stays for every row that exists today: those
     * files sit on the server's private disk under `subcontractor-documents/`
     * and are served exactly as before. Nothing is moved; the two live side
     * by side, and a row has one or the other.
     */
    public function up(): void
    {
        Schema::table('subcontractor_documents', function (Blueprint $table) {
            $table->foreignId('file_upload_id')
                ->nullable()
                ->after('file_size')
                ->constrained('file_uploads')
                ->nullOnDelete();

            $table->string('file_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subcontractor_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('file_upload_id');
        });
    }
};
