<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per invited vendor — the proposal.
     *
     * The round goes out by e-mail and comes back by e-mail, so the row keeps
     * how the vendor was asked and how the answer arrived: every price keyed
     * in later has a documented origin. Prices themselves live in
     * quotation_vendor_items (phase 3).
     */
    public function up(): void
    {
        Schema::create('quotation_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->enum('status', ['invited', 'responded', 'declined', 'awarded', 'rejected'])->default('invited');

            // How they were asked
            $table->timestamp('invited_at')->nullable();
            $table->enum('invite_method', ['email', 'whatsapp', 'phone', 'in_person'])->nullable();
            $table->string('invited_email')->nullable();

            // How the answer arrived
            $table->enum('source', ['email', 'whatsapp', 'phone', 'in_person'])->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            // Proposal terms (money in cents)
            $table->date('proposal_valid_until')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('payment_terms')->nullable();
            $table->enum('freight_type', ['cif', 'fob'])->nullable();
            $table->unsignedBigInteger('freight_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['quotation_id', 'vendor_id'], 'quotation_vendors_unique');
            $table->index('quotation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_vendors');
    }
};
