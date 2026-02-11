<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('estimate_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->string('sent_to');
            $table->string('cc')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->timestamp('opened_at')->nullable();
            $table->uuid('tracking_token')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_emails');
    }
};
