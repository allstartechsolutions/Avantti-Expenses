<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('cost_code_templates')->onDelete('cascade');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Code must be unique within the same template
            $table->unique(['template_id', 'code']);

            // Self-referencing foreign key for parent-child relationship
            $table->foreign('parent_id')->references('id')->on('cost_codes')->onDelete('cascade');

            // Index for faster queries
            $table->index(['template_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_codes');
    }
};
