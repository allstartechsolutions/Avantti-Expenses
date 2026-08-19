<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every RFQ (cotação request) the system sends, per vendor — including the
     * ones that failed, so a bounced or misconfigured send is visible rather
     * than silent. Mirrors estimate_emails.
     */
    public function up(): void
    {
        Schema::create('quotation_rfq_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('quotation_vendor_id')->nullable()
                ->constrained('quotation_vendors')->nullOnDelete();
            $table->string('sent_to');
            $table->string('cc')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->text('error')->nullable();
            $table->foreignId('sent_by')->constrained('users');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index('quotation_id');
            $table->index('quotation_vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_rfq_emails');
    }
};
