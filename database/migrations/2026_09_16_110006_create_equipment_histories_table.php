<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The audit trail of a piece of equipment — the shape of expense_change_histories. */
    public function up(): void
    {
        Schema::create('equipment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('subject_type')->nullable(); // maintenance, finding, assignment, reading
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index('equipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_histories');
    }
};
