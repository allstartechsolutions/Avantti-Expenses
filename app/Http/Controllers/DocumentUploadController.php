<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentFolder;
use App\Models\DocumentVersion;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\DocumentSettings;
use App\Services\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The endpoints the browser calls while pushing a file straight to storage.
 *
 * Nothing here accepts file content: the app hands out presigned URLs, the
 * browser uploads to Cloudflare R2 itself, and the app records the result.
 * That is what lets a multi-gigabyte drawing set be uploaded at all.
 *
 * See docs/file-repository-plan.md §2.
 */
class DocumentUploadController extends Controller
{
    public function __construct(private readonly DocumentStorageService $storage)
    {
    }

    /**
     * Create the document (or a new version of one) and return the upload plan.
     */
    public function init(Request $request): JsonResponse
    {
        $this->authorizeWrite();

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'job_site_id' => ['nullable', 'integer', 'exists:job_sites,id'],
            'folder_id' => ['nullable', 'integer', 'exists:document_folders,id'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'file_name' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(array_keys(\App\Enums\DocumentCategory::options()))],
            'is_internal' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! DocumentSettings::isAllowedFile($data['file_name'], $data['mime_type'] ?? null)) {
            return response()->json([
                'message' => __('This file type is not allowed.'),
            ], 422);
        }

        $maximum = DocumentSettings::maxUploadBytes();

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

        $project = Project::findOrFail($data['project_id']);
        $jobSite = $this->resolveJobSite($project, $data['job_site_id'] ?? null);
        $folder = $this->resolveFolder($project, $jobSite?->id, $data['folder_id'] ?? null);

        return DB::transaction(function () use ($data, $project, $jobSite, $folder) {
            $document = $this->resolveDocument($data, $project, $jobSite?->id, $folder?->id);

            $plan = $this->storage->beginUpload(
                $document,
                $data['file_name'],
                (int) $data['size_bytes'],
                $data['mime_type'] ?? null
            );

            if (! empty($data['notes'])) {
                DocumentVersion::whereKey($plan['version_id'])->update(['notes' => $data['notes']]);
            }

            return response()->json([
                'document_id' => $document->id,
                'is_new_document' => $document->wasRecentlyCreated,
            ] + $plan);
        });
    }

    /**
     * Sign another batch of part URLs for a long multipart upload.
     */
    public function parts(Request $request): JsonResponse
    {
        $this->authorizeWrite();

        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:document_versions,id'],
            'part_numbers' => ['required', 'array', 'min:1', 'max:100'],
            'part_numbers.*' => ['integer', 'min:1', 'max:10000'],
        ]);

        $version = DocumentVersion::findOrFail($data['version_id']);

        abort_unless($version->isPending() && $version->isMultipart(), 409, 'This upload is no longer open.');

        return response()->json([
            'urls' => $this->storage->presignParts(
                $version->object_key,
                $version->multipart_upload_id,
                $data['part_numbers']
            ),
        ]);
    }

    /**
     * Close the upload, verify the stored object and publish the version.
     */
    public function complete(Request $request): JsonResponse
    {
        $this->authorizeWrite();

        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:document_versions,id'],
            'parts' => ['nullable', 'array'],
            'parts.*.PartNumber' => ['required_with:parts', 'integer', 'min:1'],
            'parts.*.ETag' => ['required_with:parts', 'string', 'max:255'],
        ]);

        $version = DocumentVersion::with('document')->findOrFail($data['version_id']);

        abort_unless($version->isPending(), 409, 'This upload has already been completed.');

        $parts = collect($data['parts'] ?? [])
            ->sortBy('PartNumber')
            ->map(fn (array $part) => [
                'PartNumber' => (int) $part['PartNumber'],
                'ETag' => $part['ETag'],
            ])
            ->values()
            ->all();

        try {
            $version = $this->storage->completeUpload($version, $parts);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => __('The upload could not be completed. Please try again.'),
            ], 500);
        }

        $document = $version->document;
        $isFirstVersion = $version->version_number === 1;

        $document->update([
            'current_version_id' => $version->id,
            'current_size_bytes' => $version->size_bytes,
            'current_mime_type' => $version->mime_type,
            'current_version_number' => $version->version_number,
            'updated_by' => auth()->id(),
        ]);

        DocumentActivity::record(
            $isFirstVersion ? DocumentActivity::UPLOADED : DocumentActivity::VERSION_ADDED,
            ['document_id' => $document->id],
            ['version' => $version->version_number, 'size' => $version->size_bytes]
        );

        return response()->json([
            'document_id' => $document->id,
            'version_id' => $version->id,
            'version_number' => $version->version_number,
            'size' => $version->size_bytes,
        ]);
    }

    /**
     * The user cancelled, or the browser gave up: clean up so nothing is left
     * half-uploaded and nothing is billed for parts nobody wants.
     */
    public function abort(Request $request): JsonResponse
    {
        $this->authorizeWrite();

        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:document_versions,id'],
        ]);

        $version = DocumentVersion::with('document')->findOrFail($data['version_id']);

        abort_unless($version->isPending(), 409, 'This upload has already been completed.');

        $document = $version->document;

        $this->storage->abortUpload($version);

        // A document that never got a first version is not a document.
        if ($document && ! $document->current_version_id && $document->versions()->doesntExist()) {
            $document->forceDelete();
        }

        return response()->json(['aborted' => true]);
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    private function authorizeWrite(): void
    {
        abort_unless(
            auth()->user()?->canManageDocuments(),
            403,
            'Manager or administrator access required.'
        );
    }

    /**
     * A new document, or the one a new version was requested for. Either way
     * the location is checked against the project so an id cannot be pointed
     * at another project's folder.
     */
    private function resolveDocument(array $data, Project $project, ?int $jobSiteId, ?int $folderId): Document
    {
        if (! empty($data['document_id'])) {
            $document = Document::findOrFail($data['document_id']);

            abort_unless($document->project_id === $project->id, 403, 'Document does not belong to this project.');

            return $document;
        }

        return Document::create([
            'project_id' => $project->id,
            'job_site_id' => $jobSiteId,
            'folder_id' => $folderId,
            'name' => DocumentSettings::sanitizeFileName($data['file_name']),
            'category' => $data['category'] ?? 'other',
            'is_internal' => (bool) ($data['is_internal'] ?? false),
            'uploaded_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    private function resolveJobSite(Project $project, ?int $jobSiteId): ?JobSite
    {
        if (! $jobSiteId) {
            return null;
        }

        $jobSite = JobSite::findOrFail($jobSiteId);

        abort_unless($jobSite->project_id === $project->id, 403, 'Job site does not belong to this project.');

        return $jobSite;
    }

    private function resolveFolder(Project $project, ?int $jobSiteId, ?int $folderId): ?DocumentFolder
    {
        if (! $folderId) {
            return null;
        }

        $folder = DocumentFolder::findOrFail($folderId);

        abort_unless(
            $folder->project_id === $project->id && $folder->job_site_id === $jobSiteId,
            403,
            'Folder does not belong to this location.'
        );

        return $folder;
    }
}
