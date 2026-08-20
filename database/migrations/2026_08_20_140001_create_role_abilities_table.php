<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a company-wide role may do. One row per granted ability, named
     * `area.action` from config/permissions.php.
     *
     * Rows, not a JSON column, so "who may approve a change order?" is one
     * query — that report is part of the module.
     *
     * The admin role holds nothing here and needs nothing: it is allowed
     * everything by the resolver before this table is consulted.
     */
    public function up(): void
    {
        Schema::create('role_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('ability', 80);
            $table->timestamps();

            $table->unique(['role_id', 'ability']);
            $table->index('ability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_abilities');
    }
};
