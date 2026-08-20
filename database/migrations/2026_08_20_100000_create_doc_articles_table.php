<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A guide written by this company, alongside the ones shipped with the
     * product (config/documentation.php).
     *
     * Two sources, one index: the shipped guides always match the code they
     * describe, and each install can add its own procedures without waiting
     * for a release.
     */
    public function up(): void
    {
        Schema::create('doc_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category', 60)->default('general');
            $table->string('summary')->nullable();

            // Written in the editor, so HTML — sanitised on save and again on
            // display (App\Support\RichText).
            $table->longText('body');

            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'position']);
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_articles');
    }
};
