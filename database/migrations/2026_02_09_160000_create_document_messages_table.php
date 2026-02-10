<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_messages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('type', ['invoice', 'estimate']);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_messages');
    }
};
