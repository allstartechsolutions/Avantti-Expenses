<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The quotation and requisition numbers go out to vendors on e-mails and
     * PDFs, so two records must never carry the same one. They were indexed
     * but not unique, and the generator reads MAX()+1 without a lock — two
     * buyers creating a round at the same moment both got COT-0001, with
     * nothing to stop it.
     *
     * contracts.contract_number is already unique; this brings the buy side
     * into line.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->unique('quotation_number');
        });

        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->unique('requisition_number');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique(['quotation_number']);
        });

        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->dropUnique(['requisition_number']);
        });
    }
};
