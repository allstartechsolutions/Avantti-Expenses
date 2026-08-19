<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentActivity;
use App\Services\DocumentStorageService;
use Illuminate\Console\Command;

/**
 * Deleting a document puts it in the trash, where it stays recoverable for
 * documents.retention_days. This is what finally removes it — the row and
 * every stored object behind every version.
 *
 * Scheduled daily in routes/console.php. Set DOCUMENTS_RETENTION_DAYS empty
 * to disable and purge by hand instead.
 */
class PurgeDeletedDocuments extends Command
{
    protected $signature = 'documents:purge-deleted {--days= : Override the retention window}';

    protected $description = 'Permanently remove documents deleted longer ago than the retention window';

    public function handle(DocumentStorageService $storage): int
    {
        $days = $this->option('days') ?? config('documents.retention_days');

        if (blank($days)) {
            $this->info('Automatic purging is disabled (documents.retention_days is not set).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays((int) $days);

        $documents = Document::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->with('versions')
            ->get();

        if ($documents->isEmpty()) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        $objects = 0;

        foreach ($documents as $document) {
            foreach ($document->versions as $version) {
                $storage->deleteObject($version);
                $objects++;
            }

            DocumentActivity::record(
                DocumentActivity::PURGED,
                ['document_id' => null],
                ['document' => $document->name, 'project_id' => $document->project_id]
            );

            $document->forceDelete();
        }

        $this->info(sprintf(
            'Purged %d document(s) and %d stored file(s) deleted before %s.',
            $documents->count(),
            $objects,
            $cutoff->format('Y-m-d')
        ));

        return self::SUCCESS;
    }
}
