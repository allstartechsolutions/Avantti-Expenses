<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_schedule_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            // nullOnDelete + description snapshot so the history of a
            // deleted parcela stays readable.
            $table->foreignId('contract_schedule_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_description');
            $table->enum('action', ['created', 'updated', 'deleted', 'released']);
            $table->json('changes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('contract_id');
            $table->index('contract_schedule_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_schedule_changes');
    }
};
