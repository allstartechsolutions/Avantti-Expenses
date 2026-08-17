<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_schedule_items', function (Blueprint $table) {
            // Vistoria release of a milestone evento: who confirmed the
            // etapa as concluded, when, and any inspection notes.
            $table->timestamp('released_at')->nullable()->after('notes');
            $table->foreignId('released_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->text('release_notes')->nullable()->after('released_by');
        });
    }

    public function down(): void
    {
        Schema::table('contract_schedule_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn(['released_at', 'release_notes']);
        });
    }
};
