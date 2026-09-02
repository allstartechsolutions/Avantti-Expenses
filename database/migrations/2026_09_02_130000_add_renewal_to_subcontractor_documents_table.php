<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A vendor document gets a lifecycle, so an insurance certificate can be
     * renewed without deleting the one it replaces.
     *
     * `status` is `active` for the document that counts, `superseded` once a
     * renewal has replaced it (`superseded_by_id` points at the replacement)
     * and `archived` when it is no longer required at all — the vendor
     * stopped doing the work that needed it — with who did that and why.
     * Only active documents drive the expiry badge and the reminder e-mails,
     * which is the whole point: a renewed certificate must stop nagging.
     *
     * The four `notified_*_at` stamps follow `tasks.overdue_notified_at`: one
     * per reminder stage (30, 15 and 7 days before the date, and the day
     * after it), each written once, so the daily command can never repeat a
     * reminder. Every row that exists today becomes `active` through the
     * default and nothing on a live install changes until somebody renews or
     * archives a document.
     */
    public function up(): void
    {
        Schema::table('subcontractor_documents', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('notes');

            $table->foreignId('superseded_by_id')
                ->nullable()
                ->after('status')
                ->constrained('subcontractor_documents')
                ->nullOnDelete();

            $table->timestamp('archived_at')->nullable()->after('superseded_by_id');
            $table->foreignId('archived_by')
                ->nullable()
                ->after('archived_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('archive_reason')->nullable()->after('archived_by');

            $table->timestamp('notified_30_at')->nullable()->after('archive_reason');
            $table->timestamp('notified_15_at')->nullable()->after('notified_30_at');
            $table->timestamp('notified_7_at')->nullable()->after('notified_15_at');
            $table->timestamp('notified_expired_at')->nullable()->after('notified_7_at');

            // The badge and the reminders both scan "active, by date".
            $table->index(['status', 'expiration_date'], 'subcontractor_documents_status_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subcontractor_documents', function (Blueprint $table) {
            $table->dropIndex('subcontractor_documents_status_expiry_idx');
            $table->dropConstrainedForeignId('superseded_by_id');
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn([
                'status', 'archived_at', 'archive_reason',
                'notified_30_at', 'notified_15_at', 'notified_7_at', 'notified_expired_at',
            ]);
        });
    }
};
