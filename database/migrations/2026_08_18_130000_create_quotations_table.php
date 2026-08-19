<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The quotation round (cotação): one scope, several vendors asked to price
     * it. Raised from an approved requisition, or standalone.
     */
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()
                ->constrained('purchase_requisitions')->nullOnDelete();

            $table->string('quotation_number')->nullable();
            $table->enum('type', ['material', 'service'])->default('material');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('needed_by')->nullable();
            $table->date('responses_due_at')->nullable();
            $table->enum('status', [
                'draft', 'sent', 'comparing', 'negotiating', 'awarded', 'converted', 'cancelled',
            ])->default('draft');

            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();

            // Award
            $table->foreignId('awarded_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('award_reason')->nullable();
            $table->boolean('is_split_award')->default(false);

            // What the award became: a contract or a purchase order
            $table->string('converted_type')->nullable();
            $table->unsignedBigInteger('converted_id')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('project_id');
            $table->index('job_site_id');
            $table->index('status');
            $table->index('quotation_number');
            $table->index(['converted_type', 'converted_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
