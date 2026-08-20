<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What one person may do on one project or job site. Seeded from a
     * template on invite, then editable per person.
     */
    public function up(): void
    {
        Schema::create('membership_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->string('ability', 80);
            $table->timestamps();

            $table->unique(['membership_id', 'ability']);
            $table->index('ability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_abilities');
    }
};
