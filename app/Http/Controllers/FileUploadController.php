<?php

namespace App\Http\Controllers;

use App\Models\FileUpload;
use App\Services\DocumentSettings;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The upload handshake for everything that is not a repository document — a
 * task, a note on a task, a meeting.
 *
 * Same shape and same guarantees as DocumentUploadController: no file content
 * passes through PHP, the browser pushes straight to storage with presigned
 * URLs, and the app verifies the stored object before it counts as available.
 *
 * See docs/meetings-module-plan.md §7.
 */
class FileUploadController extends Controller
{
    public function __construct(private readonly FileUploadService $files)
    {
    }

    /**
     * Create the file row and return the upload plan.
     */
    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'string', 'max:30'],
            'target_id' => ['required', 'integer'],
            'file_name' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'mime_type' => ['nullable', 'string', 'max:255'],
        ]);

        $target = $this->files->resolveTarget($data['target_type'], (int) $data['target_id']);

        $this->authorizeUpload($target);

        if (! $this->files->isAllowedFile($data['file_name'], $data['mime_type'] ?? null)) {
            return response()->json(['message' => __('This file type is not allowed.')], 422);
        }

        $maximum = $this->files->maxBytes();

        if ($data['size_bytes'] > $maximum) {
            return response()->json([
                'message' => __('This file is larger than the :size limit.', [
                    'size' => DocumentSettings::formatBytes($maximum),
                ]),
            ], 422);
        }

        if (DocumentSettings::wouldExceedQuota((int) $data['size_bytes'])) {
            return response()->json([
                'message' => __('This install has reached its storage limit of :size.', [
                    'size' => DocumentSettings::formatBytes(DocumentSettings::storageQuotaBytes()),
                ]),
            ], 422);
        }

        return DB::transaction(fn () => response()->json($this->files->begin(
            $target,
            $data['file_name'],
            (int) $data['size_bytes'],
            $data['mime_type'] ?? null
        )));
    }

    /**
     * Sign another batch of part URLs for a long multipart upload.
     */
    public function parts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:file_uploads,id'],
            'part_numbers' => ['required', 'array', 'min:1', 'max:100'],
            'part_numbers.*' => ['integer', 'min:1', 'max:10000'],
        ]);

        $file = $this->findOwnUpload((int) $data['version_id']);

        abort_unless($file->isPending() && $file->isMultipart(), 409, 'This upload is no longer open.');

        return response()->json([
            'urls' => app(\App\Services\DocumentStorageService::class)->presignParts(
                $file->object_key,
                $file->multipart_upload_id,
                $data['part_numbers']
            ),
        ]);
    }

    /**
     * Close the upload, verify the stored object and publish the file.
     */
    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:file_uploads,id'],
            'parts' => ['nullable', 'array'],
            'parts.*.PartNumber' => ['required_with:parts', 'integer', 'min:1'],
            'parts.*.ETag' => ['required_with:parts', 'string', 'max:255'],
        ]);

        $file = $this->findOwnUpload((int) $data['version_id']);

        abort_unless($file->isPending(), 409, 'This upload has already been completed.');

        $parts = collect($data['parts'] ?? [])
            ->sortBy('PartNumber')
            ->map(fn (array $part) => [
                'PartNumber' => (int) $part['PartNumber'],
                'ETag' => $part['ETag'],
            ])
            ->values()
            ->all();

        try {
            $file = $this->files->complete($file, $parts);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => __('The upload could not be completed. Please try again.'),
            ], 500);
        }

        return response()->json([
            'file_id' => $file->id,
            'name' => $file->original_name,
            'size' => $file->size_bytes,
        ]);
    }

    /**
     * The user cancelled, or the browser gave up: clean up so nothing is left
     * half-uploaded and nothing is billed for parts nobody wants.
     */
    public function abort(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:file_uploads,id'],
        ]);

        $file = $this->findOwnUpload((int) $data['version_id']);

        abort_unless($file->isPending(), 409, 'This upload has already been completed.');

        $this->files->abort($file);

        return response()->json(['aborted' => true]);
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    private function authorizeUpload(\Illuminate\Database\Eloquent\Model $target): void
    {
        abort_unless(
            $this->files->canUploadTo($target, auth()->user()),
            403,
            'You may not attach files here.'
        );
    }

    /**
     * A pending upload row, checked against the person who started it — an id
     * from another user's upload is not a handle on it.
     */
    private function findOwnUpload(int $id): FileUpload
    {
        $file = FileUpload::with('attachable')->findOrFail($id);

        abort_unless($file->uploaded_by === auth()->id(), 403, 'This upload belongs to someone else.');

        return $file;
    }
}
