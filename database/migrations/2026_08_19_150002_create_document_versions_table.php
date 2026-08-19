<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One uploaded file. Re-uploading a document adds a version rather than
     * replacing anything, so nothing a user uploaded is ever silently lost.
     *
     * A version starts as 'pending': the row is written before the browser
     * begins pushing bytes to storage, and only becomes 'available' once the
     * upload is completed and the stored object verified.
     */
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');

            // The disk this object lives on is recorded per version, so an
            // install that moves from local storage to R2 keeps serving the
            // files uploaded before the move.
            $table->string('disk', 40);
            $table->string('object_key', 1024);

            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type')->nullable();
            $table->string('checksum')->nullable();
            $table->text('notes')->nullable();

            $table->enum('upload_status', ['pending', 'available', 'failed'])->default('pending');
            $table->string('multipart_upload_id', 512)->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['upload_status', 'created_at']);
        });

        // Now that both tables exist, close the loop from documents back to
        // the version it currently shows.
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('document_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('document_versions');
    }
};
