<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A document in the repository. The file itself lives in
     * document_versions — this row is the thing users name, move, tag and
     * share, and it survives every re-upload.
     *
     * The current_* columns are denormalised from the current version so the
     * list screen can sort and total without joining.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('document_folders')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 30)->default('other');

            // Hidden from users holding the employee role.
            $table->boolean('is_internal')->default(false);

            // Set once the first version finishes uploading. The foreign key is
            // added by the document_versions migration, which runs next.
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('current_size_bytes')->default(0);
            $table->string('current_mime_type')->nullable();
            $table->unsignedInteger('current_version_number')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'job_site_id']);
            $table->index('folder_id');
            $table->index('category');
            $table->index('name');
            $table->index('current_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
