<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->nullOnDelete();

            $table->string('estimate_number')->unique();
            $table->date('estimate_date');
            $table->enum('terms', ['net_15', 'net_30', 'net_60', 'net_90']);
            $table->date('due_date');
            $table->enum('status', ['draft', 'sent', 'accepted', 'declined'])->default('draft');

            // Message snapshot
            $table->string('message_title')->nullable();
            $table->text('message_body')->nullable();

            // Overall discount
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->unsignedBigInteger('discount_amount')->default(0);

            // Totals (stored in cents)
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);

            $table->text('notes')->nullable();

            // Future invoice conversion
            $table->unsignedBigInteger('converted_to_invoice_id')->nullable();

            // Status timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();

            // Tracking
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Indexes
            $table->index('client_id');
            $table->index('project_id');
            $table->index('status');
            $table->index('estimate_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
