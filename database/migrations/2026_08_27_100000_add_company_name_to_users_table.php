<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The firm somebody belongs to, when it is not this one.
     *
     * A projetista answering an RFI signs as "João Silva — Projetos Silva
     * Arquitetura", and an approval's signature block in Brazil carries the
     * responsible professional's firm beside their CREA registration. Neither
     * is derivable from a `users` row today: an external person has a name and
     * an e-mail and nothing that says who they work for.
     *
     * Nullable, and blank for everybody already here — staff belong to the
     * company that owns the install, so there is nothing to backfill.
     *
     * See docs/RFI-Submittals-modules.md phase 4.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('company_name');
        });
    }
};
