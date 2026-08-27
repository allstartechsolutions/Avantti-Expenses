<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aprovações — the submittal cycle.
     *
     * A material, a sample, a shop drawing or a certificate put forward for
     * somebody to accept, reject or send back. The approval is the *subject*;
     * each round of it is an `approval_revisions` row, which is where the
     * response lives.
     *
     * `budget_item_id`, not `cost_code_id`. A project's applied cost code IS
     * its `BudgetItem` — copied out of a `CostCodeTemplate` with its own code
     * and name — and `cost_codes` is the library behind that copy. See
     * docs/rfi-aprovacoes-discovery.md item 4.
     */
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_site_id')->nullable()->constrained('job_sites')->nullOnDelete();

            $table->string('number', 60);
            $table->string('title');
            $table->text('description')->nullable();

            // material | amostra | shop_drawing | prototipo
            // | ficha_tecnica | laudo_certificado | as_built
            $table->string('type', 40);

            $table->string('spec_section', 60)->nullable();   // US

            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('vendors')->nullOnDelete();

            $table->string('current_revision', 10)->default('0');
            $table->string('status', 20)->default('draft');

            $table->foreignId('ball_in_court_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();

            $table->foreignId('package_id')->nullable()->constrained('approval_packages')->nullOnDelete();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'number']);
            $table->index(['project_id', 'status']);
            $table->index(['job_site_id', 'status']);
            $table->index(['ball_in_court_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
