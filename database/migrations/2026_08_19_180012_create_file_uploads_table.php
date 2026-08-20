<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cloud sibling of the `attachments` table.
     *
     * `attachments` holds small files on the local private disk for expenses,
     * POs, requisitions and quotations. This one holds files uploaded
     * straight to the configured documents disk (R2 in production) by the
     * same presigned pipeline the repository uses, for any model that wants
     * them — tasks and their notes first.
     *
     * Deliberately generic: the deferred absorption of `attachments`
     * (docs/file-repository-plan.md) has somewhere to land.
     */
    public function up(): void
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->morphs('attachable');

            // Recorded per file, so an install that moves from the local disk
            // to R2 keeps serving what was uploaded before the move.
            $table->string('disk', 40);
            $table->string('object_key', 1024);

            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type')->nullable();

            // A row is written before the browser starts pushing bytes and
            // only becomes available once the stored object is verified.
            $table->enum('upload_status', ['pending', 'available', 'failed'])->default('pending');
            $table->string('multipart_upload_id', 512)->nullable();

            // Set when the file has also been filed into the project's
            // document repository.
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['upload_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
