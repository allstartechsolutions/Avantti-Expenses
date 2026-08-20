<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The abilities a template hands out. Same shape as role_abilities and
     * membership_abilities on purpose — the three tables are read by the same
     * resolver and rendered by the same matrix component.
     */
    public function up(): void
    {
        Schema::create('permission_template_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_template_id')->constrained()->cascadeOnDelete();
            $table->string('ability', 80);
            $table->timestamps();

            $table->unique(['permission_template_id', 'ability'], 'template_ability_unique');
            $table->index('ability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_template_abilities');
    }
};
