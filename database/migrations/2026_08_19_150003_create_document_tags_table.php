<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-form tags, shared across the whole install like cost codes, so the
     * same "as-built" or "aprovado" means the same thing on every project.
     */
    public function up(): void
    {
        Schema::create('document_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->default('slate');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_document_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'document_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_document_tag');
        Schema::dropIfExists('document_tags');
    }
};
