<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_message_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_message_id')->nullable()->constrained('document_messages')->nullOnDelete();
            $table->string('action');
            $table->string('field_changed')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_message_histories');
    }
};
