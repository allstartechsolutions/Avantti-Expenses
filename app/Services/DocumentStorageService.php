<?php

namespace App\Services;

use App\Contracts\StoredFile;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\FileUpload;
use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Everything the document repository does to storage.
 *
 * On Cloudflare R2 the browser uploads straight to the bucket with presigned
 * URLs — a multi-gigabyte drawing set never passes through PHP, so none of
 * upload_max_filesize, memory_limit or max_execution_time applies. On an
 * install without R2 the same public methods fall back to writing through the
 * local disk, with the smaller ceiling that implies.
 *
 * See docs/file-repository-plan.md and docs/deployment-cloudflare-r2.md.
 */
class DocumentStorageService
{
    /**
     * Start a new version and describe how the browser should upload it.
     *
     * @return array{version_id:int, mode:string, key:string, upload_id:?string, part_size:int, part_count:int, urls:array<int,string>}
     */
    public function beginUpload(Document $document, string $fileName, int $sizeBytes, ?string $mimeType): array
    {
        // version_number is unique per document and MAX()+1 is read without a
        // lock, so two uploads starting together would collide on the index.
        // The loser retries with the next number instead of throwing a raw
        // duplicate-key error at whoever pressed the button second.
        $version = null;
        $key = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $versionNumber = (int) $document->versions()->max('version_number') + $attempt;

            $key = DocumentSettings::objectKey(
                $document->project_id,
                $document->uuid,
                $versionNumber,
                $fileName
            );

            try {
                $version = $document->versions()->create([
                    'version_number' => $versionNumber,
                    'disk' => DocumentSettings::disk(),
                    'object_key' => $key,
                    'original_name' => $fileName,
                    'size_bytes' => $sizeBytes,
                    'mime_type' => $mimeType,
                    'upload_status' => DocumentVersion::STATUS_PENDING,
                    'uploaded_by' => auth()->id(),
                ]);

                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 3) {
                    throw $e;
                }
            }
        }

        return ['version_id' => $version->id] + $this->planUpload($version, $key, $sizeBytes, $mimeType);
    }

    /**
     * How the browser should push one already-created row's object up.
     *
     * Split out of beginUpload() so anything else that stores a file — a task
     * attachment, a note's photo — goes up through exactly the same presign,
     * multipart and part-size rules as a repository document, rather than
     * growing a second copy of them.
     *
     * @return array{mode:string, key:string, upload_id:?string, part_size:int, part_count:int, urls:array<int,string>}
     */
    public function planUpload(StoredFile $file, string $key, int $sizeBytes, ?string $mimeType): array
    {
        if (! DocumentSettings::isCloudConfigured()) {
            // The Livewire component uploads the file itself and then calls
            // storeLocalUpload(); there is nothing to presign.
            return [
                'mode' => 'local',
                'key' => $key,
                'upload_id' => null,
                'part_size' => 0,
                'part_count' => 0,
                'urls' => [],
            ];
        }

        if (! DocumentSettings::needsMultipart($sizeBytes)) {
            return [
                'mode' => 'single',
                'key' => $key,
                'upload_id' => null,
                'part_size' => $sizeBytes,
                'part_count' => 1,
                'urls' => [1 => $this->presignPut($key, $mimeType)],
            ];
        }

        $partSize = DocumentSettings::partSize();
        $partCount = (int) max(1, ceil($sizeBytes / $partSize));

        $uploadId = $this->client()->createMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ContentType' => $mimeType ?: 'application/octet-stream',
        ])['UploadId'];

        $file->update(['multipart_upload_id' => $uploadId]);

        return [
            'mode' => 'multipart',
            'key' => $key,
            'upload_id' => $uploadId,
            'part_size' => $partSize,
            'part_count' => $partCount,
            'urls' => $this->presignParts($key, $uploadId, range(1, min($partCount, 50))),
        ];
    }

    /**
     * Sign the next batch of part URLs. Signatures expire, so a long upload
     * asks for them in batches rather than all at the start.
     *
     * @param  array<int, int>  $partNumbers
     * @return array<int, string>
     */
    public function presignParts(string $key, string $uploadId, array $partNumbers): array
    {
        $urls = [];

        foreach ($partNumbers as $partNumber) {
            $command = $this->client()->getCommand('UploadPart', [
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'UploadId' => $uploadId,
                'PartNumber' => $partNumber,
            ]);

            $urls[$partNumber] = (string) $this->client()
                ->createPresignedRequest($command, '+6 hours')
                ->getUri();
        }

        return $urls;
    }

    /**
     * A presigned PUT for a file small enough to go up in one request.
     */
    public function presignPut(string $key, ?string $mimeType): string
    {
        $command = $this->client()->getCommand('PutObject', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ContentType' => $mimeType ?: 'application/octet-stream',
        ]);

        return (string) $this->client()
            ->createPresignedRequest($command, '+6 hours')
            ->getUri();
    }

    /**
     * Finish an upload: close the multipart if there was one, confirm the
     * object really is there and really is the size claimed, then mark the
     * version available. The browser's numbers are never trusted.
     *
     * @param  array<int, array{PartNumber:int, ETag:string}>  $parts
     */
    public function completeUpload(StoredFile $version, array $parts = [], ?int $maxBytes = null): StoredFile
    {
        if ($version->isMultipart()) {
            $this->client()->completeMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $version->object_key,
                'UploadId' => $version->multipart_upload_id,
                'MultipartUpload' => ['Parts' => array_values($parts)],
            ]);
        }

        $head = $this->headObject($version);

        if (! $head) {
            $version->update(['upload_status' => StoredFile::STATUS_FAILED]);

            throw new RuntimeException('The uploaded file could not be found in storage.');
        }

        // The size checked at init() was the browser's word for it, and the
        // presigned URL does not constrain what actually gets PUT. This is the
        // first point where the real size is known, so it is where the ceiling
        // is enforced — and an oversized object is removed rather than kept.
        $maximum = $maxBytes ?? DocumentSettings::maxUploadBytes();

        if ($head['size'] > $maximum) {
            $this->deleteObject($version);
            $version->update(['upload_status' => StoredFile::STATUS_FAILED]);

            throw new RuntimeException(sprintf(
                'The uploaded file is %s, over the %s limit.',
                DocumentSettings::formatBytes($head['size']),
                DocumentSettings::formatBytes($maximum)
            ));
        }

        $version->update([
            'size_bytes' => $head['size'],
            'mime_type' => $head['mime'] ?: $version->mime_type,
            'checksum' => $head['etag'],
            'upload_status' => StoredFile::STATUS_AVAILABLE,
            'multipart_upload_id' => null,
        ]);

        return $version->refresh();
    }

    /**
     * Store a file that came through PHP — the local-disk path, and the way
     * every install without R2 uploads.
     */
    public function storeLocalUpload(StoredFile $version, UploadedFile $file): StoredFile
    {
        $disk = Storage::disk($version->disk);

        $stream = fopen($file->getRealPath(), 'rb');

        try {
            $disk->put($version->object_key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $version->update([
            'size_bytes' => (int) $disk->size($version->object_key),
            'mime_type' => $disk->mimeType($version->object_key) ?: $file->getMimeType(),
            'checksum' => md5_file($file->getRealPath()),
            'upload_status' => StoredFile::STATUS_AVAILABLE,
        ]);

        return $version->refresh();
    }

    /**
     * Give up on an upload and leave nothing behind — an unfinished multipart
     * upload is billed by R2 until it is aborted.
     */
    public function abortUpload(StoredFile $version): void
    {
        if ($version->isMultipart() && $this->isCloudVersion($version)) {
            try {
                $this->client()->abortMultipartUpload([
                    'Bucket' => $this->bucket(),
                    'Key' => $version->object_key,
                    'UploadId' => $version->multipart_upload_id,
                ]);
            } catch (\Throwable) {
                // Already aborted or expired: nothing left to clean up.
            }
        }

        $this->deleteObject($version);

        $version->delete();
    }

    /**
     * What storage says about the object, or null if it is not there.
     *
     * @return array{size:int, mime:?string, etag:?string}|null
     */
    public function headObject(StoredFile $version): ?array
    {
        if ($this->isCloudVersion($version)) {
            try {
                $result = $this->client()->headObject([
                    'Bucket' => $this->bucket(),
                    'Key' => $version->object_key,
                ]);
            } catch (\Throwable) {
                return null;
            }

            return [
                'size' => (int) $result['ContentLength'],
                'mime' => $result['ContentType'] ?? null,
                'etag' => trim((string) ($result['ETag'] ?? ''), '"') ?: null,
            ];
        }

        $disk = $this->disk($version);

        if (! $disk->exists($version->object_key)) {
            return null;
        }

        return [
            'size' => (int) $disk->size($version->object_key),
            'mime' => $disk->mimeType($version->object_key) ?: null,
            'etag' => null,
        ];
    }

    /**
     * A short-lived URL the browser can fetch the file from directly. Null on
     * the local disk, where the file has to be streamed by a controller.
     */
    public function temporaryUrl(StoredFile $version, string $downloadName, bool $inline = false): ?string
    {
        if (! $this->isCloudVersion($version)) {
            return null;
        }

        $disposition = $inline ? 'inline' : 'attachment';

        return $this->disk($version)->temporaryUrl(
            $version->object_key,
            now()->addSeconds(DocumentSettings::presignTtl()),
            [
                'ResponseContentDisposition' => sprintf(
                    '%s; filename="%s"',
                    $disposition,
                    str_replace('"', '', $downloadName)
                ),
                'ResponseContentType' => $version->mime_type ?: 'application/octet-stream',
            ]
        );
    }

    /**
     * Copy a stored object to a new key on the same disk — how restoring an
     * older version works, so the two versions never share one object and
     * deleting either cannot pull the file out from under the other.
     *
     * Cloudflare R2 does this server side: nothing is downloaded or re-uploaded.
     */
    public function copyObject(StoredFile $source, string $targetKey): void
    {
        $disk = Storage::disk($source->disk);

        if (! $disk->exists($source->object_key)) {
            throw new RuntimeException('The file for that version is no longer in storage.');
        }

        $disk->copy($source->object_key, $targetKey);
    }

    /**
     * Remove the stored object for one version. Missing objects are not an
     * error — the point is that it is gone.
     */
    public function deleteObject(StoredFile $version): void
    {
        try {
            $this->disk($version)->delete($version->object_key);
        } catch (\Throwable) {
            // Nothing to delete, or storage is unreachable; the row is what
            // the application trusts and the prune command will retry.
        }
    }

    /**
     * Abort every multipart upload older than the configured window and drop
     * the rows that were never finished. Called by documents:prune-uploads.
     *
     * @return array{aborted:int, versions:int, documents:int}
     */
    public function pruneStaleUploads(): array
    {
        $cutoff = now()->subHours((int) config('documents.stale_upload_hours', 24));

        $stale = DocumentVersion::whereIn('upload_status', [
            DocumentVersion::STATUS_PENDING,
            DocumentVersion::STATUS_FAILED,
        ])->where('created_at', '<', $cutoff)->get();

        $aborted = 0;

        foreach ($stale as $version) {
            if ($version->isMultipart()) {
                $aborted++;
            }

            $this->abortUpload($version);
        }

        // Multipart uploads R2 still holds with no version row behind them:
        // an init() whose transaction rolled back leaves exactly this, and
        // nothing else would ever clean it up.
        $orphanedUploads = $this->abortOrphanedMultipartUploads($cutoff);

        // A document whose first upload never finished has nothing to show and
        // no file behind it. It is removed outright rather than soft deleted:
        // the trash is for documents someone actually had, not for the debris
        // of a cancelled upload.
        $orphans = Document::withTrashed()
            ->whereNull('current_version_id')
            ->where('created_at', '<', $cutoff)
            ->doesntHave('versions')
            ->get();

        foreach ($orphans as $orphan) {
            $orphan->forceDelete();
        }

        return [
            'aborted' => $aborted + $orphanedUploads,
            'versions' => $stale->count(),
            'documents' => $orphans->count(),
        ];
    }

    /**
     * Ask storage what it is still holding and abort anything old that the
     * application has no record of.
     */
    private function abortOrphanedMultipartUploads(\Carbon\Carbon $cutoff): int
    {
        if (! DocumentSettings::isCloudConfigured()) {
            return 0;
        }

        try {
            $result = $this->client()->listMultipartUploads(['Bucket' => $this->bucket()]);
        } catch (\Throwable) {
            return 0;
        }

        // Every multipart this application knows about — document versions and
        // the files any other module has in flight. Sweeping the bucket while
        // only knowing half of them would abort live uploads.
        $known = DocumentVersion::whereNotNull('multipart_upload_id')
            ->pluck('multipart_upload_id')
            ->merge(
                FileUpload::withTrashed()
                    ->whereNotNull('multipart_upload_id')
                    ->pluck('multipart_upload_id')
            )
            ->all();

        $aborted = 0;

        foreach ($result['Uploads'] ?? [] as $upload) {
            if (in_array($upload['UploadId'], $known, true)) {
                continue;
            }

            if (isset($upload['Initiated']) && $upload['Initiated'] > $cutoff) {
                continue;
            }

            try {
                $this->client()->abortMultipartUpload([
                    'Bucket' => $this->bucket(),
                    'Key' => $upload['Key'],
                    'UploadId' => $upload['UploadId'],
                ]);

                $aborted++;
            } catch (\Throwable) {
                // Gone already, or storage is unreachable; the next run retries.
            }
        }

        return $aborted;
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    private function disk(StoredFile $version): Filesystem
    {
        return Storage::disk($version->disk ?: DocumentSettings::disk());
    }

    /**
     * Is this version's object on an S3-compatible disk? Versions uploaded
     * before an install moved to R2 stay on the disk they were written to.
     */
    private function isCloudVersion(StoredFile $version): bool
    {
        return config("filesystems.disks.{$version->disk}.driver") === 's3';
    }

    private function client(): S3Client
    {
        $disk = $this->cloudDiskName();

        $config = config("filesystems.disks.{$disk}");

        return new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'auto',
            'endpoint' => $config['endpoint'],
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);
    }

    private function bucket(): string
    {
        return (string) config("filesystems.disks.{$this->cloudDiskName()}.bucket");
    }

    private function cloudDiskName(): string
    {
        $disk = DocumentSettings::disk();

        if (config("filesystems.disks.{$disk}.driver") !== 's3') {
            throw new RuntimeException('Cloud storage is not configured for documents.');
        }

        return $disk;
    }
}
