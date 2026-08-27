<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The extra facts a laudo or a certificate carries.
     *
     * `laudo_certificado` is first-class rather than an afterthought: INMETRO
     * conformity certificates and laudos de ensaio are the most-used approval
     * type in Brazilian residential and commercial work, and the two questions
     * asked of one — who issued it, and is it still valid — have nowhere to
     * live on the approval itself.
     *
     * `valid_until` is the one that matters in practice. A certificate that
     * expired between approval and delivery is a problem somebody needs to see
     * coming.
     */
    public function up(): void
    {
        Schema::create('approval_certificate_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_id')->constrained()->cascadeOnDelete();

            $table->string('issuing_body');
            $table->string('certificate_number', 120)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('valid_until')->nullable();

            $table->timestamps();

            $table->unique('approval_id');
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_certificate_details');
    }
};
