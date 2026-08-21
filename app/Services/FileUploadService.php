<?php

namespace App\Services;

use App\Models\DocArticle;
use App\Models\FileUpload;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Files attached to something that is not a repository document — a task, a
 * note on a task, a meeting.
 *
 * All of the storage work is DocumentStorageService's: the same presigned
 * direct-to-bucket upload, the same multipart rules, the same verification of
 * the stored object. This class only decides what may be uploaded, where the
 * object key goes and who is allowed to do it.
 *
 * See docs/meetings-module-plan.md §7.
 */
class FileUploadService
{
    /**
     * What may own a file, and where its objects live. Keys are what the
     * browser sends as target_type; the browser never sees a class name and
     * never chooses a path.
     */
    private const TARGETS = [
        'task' => Task::class,
        'task_note' => TaskNote::class,
        'meeting' => Meeting::class,
        'doc_article' => DocArticle::class,
    ];

    /** Where documentation images live in the bucket. */
    public const LIBRARY_PREFIX = 'documentation';

    public function __construct(private readonly DocumentStorageService $storage)
    {
    }

    // =========================================================================
    // TARGETS
    // =========================================================================

    /**
     * The model a file is being attached to. Unknown types are refused rather
     * than guessed at.
     */
    public function resolveTarget(string $type, int $id): Model
    {
        $class = self::TARGETS[$type] ?? null;

        abort_if($class === null, 422, 'Unknown upload target.');

        return $class::findOrFail($id);
    }

    /**
     * Who may attach a file here.
     *
     * Tasks and their notes are open to any signed-in user — the owner's
     * decision (docs/meetings-module-plan.md §1): anyone raises a task, adds
     * notes and attaches evidence. A meeting's own attachments follow whoever
     * may run the meeting, and a guide's follow whoever may write one.
     */
    public function canUploadTo(Model $target, ?User $user): bool
    {
        if ($user === null || ! $user->isActive()) {
            return false;
        }

        $resolver = app(PermissionResolver::class);

        // F2: each target asks for the grant that owns it, rather than for a
        // role name. Both are seeded to exactly who could upload before.
        return match (true) {
            $target instanceof Meeting => $resolver->allows($user, 'meetings.edit'),
            $target instanceof DocArticle => $resolver->allows($user, 'documentation.create')
                || $resolver->allows($user, 'documentation.edit'),
            $target instanceof Task, $target instanceof TaskNote => true,
            default => false,
        };
    }

    // =========================================================================
    // UPLOADING
    // =========================================================================

    /**
     * Create the row and describe how the browser should push the bytes.
     *
     * @return array{version_id:int, mode:string, key:string, upload_id:?string, part_size:int, part_count:int, urls:array<int,string>}
     */
    public function begin(Model $target, string $fileName, int $sizeBytes, ?string $mimeType): array
    {
        $file = new FileUpload([
            'uuid' => (string) Str::uuid(),
            'disk' => DocumentSettings::disk(),
            'original_name' => DocumentSettings::sanitizeFileName($fileName),
            'size_bytes' => $sizeBytes,
            'mime_type' => $mimeType,
            'upload_status' => FileUpload::STATUS_PENDING,
            'uploaded_by' => auth()->id(),
        ]);

        $file->attachable()->associate($target);
        $file->object_key = $this->objectKey($target, $file->uuid, $file->original_name);
        $file->save();

        return ['version_id' => $file->id] + $this->storage->planUpload(
            $file,
            $file->object_key,
            $sizeBytes,
            $mimeType
        );
    }

    /**
     * Close the upload and verify what really landed in storage — the
     * browser's word for the size is never taken.
     */
    public function complete(FileUpload $file, array $parts = []): FileUpload
    {
        return $this->storage->completeUpload($file, $parts, $this->maxBytes());
    }

    /** The Livewire path, for installs with no cloud storage configured. */
    public function storeLocal(FileUpload $file, UploadedFile $upload): FileUpload
    {
        return $this->storage->storeLocalUpload($file, $upload);
    }

    /**
     * Give up and leave nothing behind — an unfinished multipart upload is
     * billed by R2 until it is aborted.
     */
    public function abort(FileUpload $file): void
    {
        $this->storage->abortUpload($file);

        // abortUpload() soft deletes the row; an upload nobody finished is
        // debris rather than history, so it goes for good.
        $file->forceDelete();
    }

    /** A short-lived URL the browser downloads or previews the file from. */
    public function temporaryUrl(FileUpload $file, bool $inline = false): ?string
    {
        return $this->storage->temporaryUrl($file, $file->original_name, $inline);
    }

    /**
     * Abort every upload that was started and never finished, and drop the
     * rows behind them — R2 bills the parts of an incomplete multipart upload
     * until it is aborted. Called by documents:prune-uploads.
     *
     * @return array{aborted:int, files:int}
     */
    public function pruneStaleUploads(): array
    {
        $cutoff = now()->subHours((int) config('documents.stale_upload_hours', 24));

        $stale = FileUpload::whereIn('upload_status', [
            FileUpload::STATUS_PENDING,
            FileUpload::STATUS_FAILED,
        ])->where('created_at', '<', $cutoff)->get();

        $aborted = 0;

        foreach ($stale as $file) {
            if ($file->isMultipart()) {
                $aborted++;
            }

            $this->abort($file);
        }

        return ['aborted' => $aborted, 'files' => $stale->count()];
    }

    /**
     * Put a file the application already holds into storage, under a key we
     * choose, and record it as a library asset owned by no particular record.
     *
     * Used by documentation:sync-images, which pushes the images a shipped
     * guide references. Bytes go through PHP here rather than through a
     * presigned URL — there is no browser involved, and the files are small.
     */
    public function storeLibraryFile(string $absolutePath, string $key, ?int $uploadedBy = null): FileUpload
    {
        abort_unless(is_file($absolutePath), 404, 'File not found: '.$absolutePath);

        $disk = DocumentSettings::disk();
        $contents = file_get_contents($absolutePath);

        Storage::disk($disk)->put($key, $contents);

        return FileUpload::updateOrCreate(
            ['object_key' => $key],
            [
                'uuid' => (string) Str::uuid(),
                'attachable_type' => null,
                'attachable_id' => null,
                'disk' => $disk,
                'original_name' => basename($absolutePath),
                'size_bytes' => strlen($contents),
                'mime_type' => mime_content_type($absolutePath) ?: null,
                'checksum' => md5($contents),
                'upload_status' => FileUpload::STATUS_AVAILABLE,
                'uploaded_by' => $uploadedBy,
            ]
        );
    }

    /** The stored image for a path a guide referenced, if it has been synced. */
    public function libraryFile(string $key): ?FileUpload
    {
        return FileUpload::whereNull('attachable_type')
            ->where('object_key', $key)
            ->where('upload_status', FileUpload::STATUS_AVAILABLE)
            ->first();
    }

    // =========================================================================
    // LIMITS
    // =========================================================================

    /**
     * Task attachments are capped well below repository documents: a photo, a
     * marked-up PDF or a spreadsheet, not a drawing set. The cap can never
     * exceed what the repository itself allows.
     */
    public function maxBytes(): int
    {
        return min(
            (int) config('tasks.max_upload_bytes', 100 * 1024 * 1024),
            DocumentSettings::maxUploadBytes()
        );
    }

    public function isAllowedFile(string $fileName, ?string $mimeType = null): bool
    {
        return DocumentSettings::isAllowedFile($fileName, $mimeType);
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Server-generated, never derived from what the browser sent beyond the
     * sanitised file name.
     */
    private function objectKey(Model $target, string $fileUuid, string $fileName): string
    {
        $prefix = match (true) {
            $target instanceof Task => 'tasks/'.$target->uuid,
            $target instanceof TaskNote => 'tasks/'.($target->task?->uuid ?? 'unknown').'/notes/'.$target->id,
            $target instanceof Meeting => 'meetings/'.$target->id,
            $target instanceof DocArticle => self::LIBRARY_PREFIX.'/articles/'.$target->id,
            default => throw new RuntimeException('Unsupported upload target.'),
        };

        return $prefix.'/'.$fileUuid.'/'.$fileName;
    }
}
