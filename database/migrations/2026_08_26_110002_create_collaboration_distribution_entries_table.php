<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who gets a copy. Polymorphic, so an RFI and an approval share one list.
     *
     * Either `user_id` is set — somebody with a login, staff or guest — or the
     * name and e-mail are, for a copy to a person who has no account and does
     * not need one. Both shapes are ordinary: the fiscalização often wants the
     * document without wanting a login.
     *
     * `role` is what they are on this document (projetista, fornecedor,
     * fiscalização, cliente), not a permission. Permissions come from the
     * membership; this is a label on the transmittal.
     */
    public function up(): void
    {
        Schema::create('collaboration_distribution_entries', function (Blueprint $table) {
            $table->id();

            // Named explicitly: the generated name would be
            // collaboration_distribution_entries_distributable_type_distributable_id_index,
            // 76 characters, and MySQL stops at 64.
            $table->morphs('distributable', 'ccde_distributable_index');

            // constrained() indexes user_id already; no second index here.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->string('role', 40)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_distribution_entries');
    }
};
