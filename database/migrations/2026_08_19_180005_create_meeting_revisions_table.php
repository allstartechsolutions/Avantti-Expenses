<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A published minute is locked. When an admin has to correct one, the
     * correction is recorded here with a reason and shown on the document
     * and in the PDF — a record that changes silently is worth nothing.
     */
    public function up(): void
    {
        Schema::create('meeting_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');

            $table->foreignId('revised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_revisions');
    }
};
