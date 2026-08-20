<?php

namespace App\Console\Commands;

use App\Services\DocumentStorageService;
use App\Services\FileUploadService;
use Illuminate\Console\Command;

/**
 * Cloudflare R2 bills the parts of a multipart upload that was never
 * completed, and a half-uploaded row is dead weight in the UI. This clears
 * both, for repository documents and for the files other modules attach.
 * Scheduled hourly in routes/console.php.
 */
class PruneDocumentUploads extends Command
{
    protected $signature = 'documents:prune-uploads';

    protected $description = 'Abort stale document uploads and remove the rows they left behind';

    public function handle(DocumentStorageService $storage, FileUploadService $files): int
    {
        $result = $storage->pruneStaleUploads();

        $this->info(sprintf(
            'Aborted %d multipart upload(s); removed %d unfinished version(s) and %d empty document(s).',
            $result['aborted'],
            $result['versions'],
            $result['documents']
        ));

        $attachments = $files->pruneStaleUploads();

        $this->info(sprintf(
            'Aborted %d attachment upload(s); removed %d unfinished file(s).',
            $attachments['aborted'],
            $attachments['files']
        ));

        return self::SUCCESS;
    }
}
