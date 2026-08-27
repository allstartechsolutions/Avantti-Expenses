<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A signature on a document, and the evidence that it is one.
     *
     * Nothing in this codebase signed anything before: a daily report has
     * `locked_at`, which records that somebody closed it, not that somebody
     * put their name to what it said.
     *
     * `payload_hash` is what makes this evidentiary rather than decorative. It
     * is a hash of the record as it stood at the moment of signing, so the
     * question "is this still the document that was signed?" has an answer. A
     * signature row with no hash proves only that a button was pressed.
     *
     * `signer_document` carries the CREA/CAU registration and `art_number` the
     * ART — a shop drawing approved in Brazil without the responsible
     * professional's registration on it is not worth much. Both are nullable
     * because a US install has neither, and neither does an internal reviewer.
     *
     * `method` is a string, not an enum, so gov.br and ICP-Brasil can be added
     * later without a migration. Today it only ever holds 'drawn'.
     */
    public function up(): void
    {
        Schema::create('collaboration_signatures', function (Blueprint $table) {
            $table->id();

            $table->morphs('signable');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name');
            $table->string('signer_document')->nullable();   // CREA / CAU
            $table->string('art_number')->nullable();
            $table->string('method', 20)->default('drawn');  // drawn | gov_br | icp_brasil

            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();    // 45 = IPv6
            $table->string('payload_hash', 64);              // sha256, hex

            $table->timestamps();

            $table->index('signed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_signatures');
    }
};
