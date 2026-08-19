<?php

namespace App\Livewire\Concerns;

use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentFolder;
use App\Models\DocumentShare;
use App\Models\DocumentTag;
use App\Models\DocumentVersion;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\DocumentSettings;
use App\Services\DocumentStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Everything the project and job site file repositories do, so the two pages
 * stay identical by construction (docs/project-jobsite-parity-rule.md).
 *
 * The host component supplies the location through contextProject() and
 * contextJobSite(); the job site page fixes the location, the project page
 * lets the user switch between the project's own folders, a job site's
 * folders, or a flat list of everything.
 *
 * Uploads are not handled here: the browser sends the bytes straight to
 * storage through DocumentUploadController, and calls documentUploaded()
 * when it is finished. See docs/file-repository-plan.md.
 */
trait ManagesDocuments
{
    use AuthorizesDocuments;

    // Filters
    public string $search = '';
    public string $categoryFilter = '';
    public string $tagFilter = '';
    public string $uploaderFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    /** '' = every location (flat list), 'project', or a job site id. */
    public string $locationFilter = 'project';

    /** 'list' or 'grid'. */
    public string $viewMode = 'list';

    /** The folder being browsed; null is the root of the current location. */
    public ?int $folderId = null;

    /** @var array<int, int> */
    public array $selected = [];

    // Upload panel
    public string $uploadCategory = 'other';
    public string $uploadTags = '';
    public bool $uploadIsInternal = false;
    public ?int $uploadDocumentId = null;
    public string $uploadVersionNotes = '';

    /** Files chosen on an install without cloud storage, uploaded through PHP. */
    public $localUploads = [];

    // Folder modal
    public ?int $editingFolderId = null;
    public string $folderName = '';
    public string $folderDescription = '';

    // Rename / move / edit modal
    public ?int $editingDocumentId = null;
    public string $documentName = '';
    public string $documentDescription = '';
    public string $documentCategory = 'other';
    public string $documentTags = '';
    public bool $documentIsInternal = false;
    public string $documentFolderId = '';
    public string $documentJobSiteId = '';

    // Bulk actions
    public string $bulkFolderId = '';
    public string $bulkCategory = '';

    // Detail view
    public ?int $viewingDocumentId = null;

    // Share links
    public ?int $sharingDocumentId = null;
    public ?int $sharingFolderId = null;
    public string $shareExpiresAt = '';
    public string $sharePassword = '';
    public bool $shareAllowDownload = true;
    public string $shareMaxDownloads = '';
    public string $shareRecipient = '';
    public ?string $createdShareUrl = null;

    // Trash
    public bool $showTrash = false;

    /**
     * Runs on every request, before anything reads state.
     *
     * folderId is a query-string property, so it arrives from the browser and
     * may point at a folder in another project or another location. Anything
     * that does not belong to what this page is showing is dropped back to the
     * root rather than trusted — otherwise new folders and uploads get filed
     * somewhere neither tree can reach.
     */
    public function bootedManagesDocuments(): void
    {
        if (! $this->folderId) {
            return;
        }

        $folder = DocumentFolder::find($this->folderId);

        $belongsHere = $folder
            && $folder->project_id === $this->contextProject()->id
            && $folder->job_site_id === $this->activeJobSiteId();

        if (! $belongsHere) {
            $this->folderId = null;
        }
    }

    // =========================================================================
    // CONTEXT — supplied by the host component
    // =========================================================================

    abstract protected function contextProject(): Project;

    abstract protected function contextJobSite(): ?JobSite;

    /**
     * True on the job site page, where the location is fixed and the location
     * selector must not appear.
     */
    public function isJobSiteContext(): bool
    {
        return $this->contextJobSite() !== null;
    }

    /**
     * The job site the current view is filed under, or null for project level.
     * Null with $locationFilter === '' means "every location", which is a flat
     * list rather than a folder tree.
     */
    protected function activeJobSiteId(): ?int
    {
        if ($jobSite = $this->contextJobSite()) {
            return $jobSite->id;
        }

        return is_numeric($this->locationFilter) ? (int) $this->locationFilter : null;
    }

    /**
     * What the current view is filed under, for the breadcrumb.
     */
    public function activeLocationName(): string
    {
        if ($jobSite = $this->contextJobSite()) {
            return $jobSite->job_site_name;
        }

        if ($this->locationFilter === 'project') {
            return __('Project (General)');
        }

        if (is_numeric($this->locationFilter)) {
            return JobSite::find((int) $this->locationFilter)?->job_site_name ?? __('Job Site');
        }

        return __('All Locations');
    }

    /**
     * In "All Locations" the folder tree makes no sense — documents from
     * several trees are shown together, with the folder as a column.
     */
    public function isFlatMode(): bool
    {
        return ! $this->isJobSiteContext() && $this->locationFilter === '';
    }

    // =========================================================================
    // FILTERS
    // =========================================================================

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTagFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUploaderFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    /**
     * Switching location changes which folder tree is on screen, so the
     * current folder cannot be carried over.
     */
    public function updatedLocationFilter(): void
    {
        $this->folderId = null;
        $this->selected = [];
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return filled($this->search)
            || filled($this->categoryFilter)
            || filled($this->tagFilter)
            || filled($this->uploaderFilter)
            || filled($this->dateFrom)
            || filled($this->dateTo);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = '';
        $this->tagFilter = '';
        $this->uploaderFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode === 'grid' ? 'grid' : 'list';
    }

    public function toggleTrash(): void
    {
        $this->showTrash = ! $this->showTrash;
        $this->selected = [];
        $this->resetPage();
    }

    // =========================================================================
    // FOLDER NAVIGATION
    // =========================================================================

    public function openFolder(?int $folderId): void
    {
        if ($folderId) {
            $folder = DocumentFolder::findOrFail($folderId);
            $this->assertFolderInLocation($folder);
        }

        $this->folderId = $folderId;
        $this->selected = [];
        $this->resetPage();
    }

    public function goUp(): void
    {
        $this->openFolder($this->currentFolder?->parent_id);
    }

    // =========================================================================
    // FOLDER CRUD
    // =========================================================================

    public function openFolderModal(?int $folderId = null): void
    {
        $this->authorizeDocumentWrite();

        $this->resetValidation();
        $this->editingFolderId = $folderId;

        if ($folderId) {
            $folder = DocumentFolder::findOrFail($folderId);
            $this->assertFolderInLocation($folder);
            $this->folderName = $folder->name;
            $this->folderDescription = (string) $folder->description;
        } else {
            $this->folderName = '';
            $this->folderDescription = '';
        }

        $this->dispatch('open-modal', 'document-folder-modal');
    }

    public function saveFolder(): void
    {
        $this->authorizeDocumentWrite();

        $this->validate([
            'folderName' => ['required', 'string', 'max:120'],
            'folderDescription' => ['nullable', 'string', 'max:500'],
        ], [], [
            'folderName' => __('folder name'),
            'folderDescription' => __('description'),
        ]);

        if ($this->isFlatMode()) {
            $this->addError('folderName', __('Choose a location before creating a folder.'));

            return;
        }

        $parent = $this->currentFolder;

        if (! $this->editingFolderId && $parent && $parent->depth() >= DocumentFolder::MAX_DEPTH) {
            $this->addError('folderName', __('Folders cannot be nested more than :depth levels deep.', [
                'depth' => DocumentFolder::MAX_DEPTH,
            ]));

            return;
        }

        $duplicate = DocumentFolder::forLocation($this->contextProject()->id, $this->activeJobSiteId())
            ->where('parent_id', $this->editingFolderId ? DocumentFolder::find($this->editingFolderId)?->parent_id : $parent?->id)
            ->where('name', $this->folderName)
            ->when($this->editingFolderId, fn (Builder $q) => $q->whereKeyNot($this->editingFolderId))
            ->exists();

        if ($duplicate) {
            $this->addError('folderName', __('A folder with this name already exists here.'));

            return;
        }

        if ($this->editingFolderId) {
            $folder = DocumentFolder::findOrFail($this->editingFolderId);
            $this->assertFolderInLocation($folder);

            $folder->update([
                'name' => $this->folderName,
                'description' => $this->folderDescription ?: null,
                'updated_by' => auth()->id(),
            ]);

            DocumentActivity::record(DocumentActivity::FOLDER_RENAMED, ['folder_id' => $folder->id], [
                'name' => $folder->name,
            ]);

            session()->flash('message', __('Folder updated.'));
        } else {
            $folder = DocumentFolder::create([
                'project_id' => $this->contextProject()->id,
                'job_site_id' => $this->activeJobSiteId(),
                'parent_id' => $parent?->id,
                'name' => $this->folderName,
                'description' => $this->folderDescription ?: null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            DocumentActivity::record(DocumentActivity::FOLDER_CREATED, ['folder_id' => $folder->id], [
                'name' => $folder->name,
            ]);

            session()->flash('message', __('Folder created.'));
        }

        $this->closeFolderModal();
    }

    public function closeFolderModal(): void
    {
        $this->dispatch('close-modal', 'document-folder-modal');
        $this->editingFolderId = null;
        $this->folderName = '';
        $this->folderDescription = '';
        $this->resetValidation();
    }

    /**
     * Deleting a folder never destroys documents: they move up to the parent
     * folder, so nothing disappears because a container was tidied away.
     */
    public function deleteFolder(int $folderId): void
    {
        $this->authorizeDocumentDelete();

        $folder = DocumentFolder::with('children')->findOrFail($folderId);
        $this->assertFolderInLocation($folder);

        DB::transaction(function () use ($folder) {
            // withTrashed: a document in the trash still remembers its folder,
            // and restoring it into a folder that no longer exists would put
            // it somewhere the tree cannot reach.
            Document::withTrashed()->where('folder_id', $folder->id)->update(['folder_id' => $folder->parent_id]);
            DocumentFolder::where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);

            $revoked = $this->revokeSharesFor($folder);

            DocumentActivity::record(DocumentActivity::FOLDER_DELETED, ['folder_id' => null], [
                'name' => $folder->name,
                'project_id' => $folder->project_id,
                'shares_revoked' => $revoked,
            ]);

            $folder->delete();
        });

        if ($this->folderId === $folderId) {
            $this->folderId = $folder->parent_id;
        }

        session()->flash('message', __('Folder deleted. Its contents moved up one level.'));
    }

    // =========================================================================
    // UPLOADS
    // =========================================================================

    public function openUploadModal(?int $documentId = null): void
    {
        $this->authorizeDocumentWrite();

        $this->uploadDocumentId = $documentId;
        $this->uploadVersionNotes = '';
        $this->uploadCategory = 'other';
        $this->uploadTags = '';
        $this->uploadIsInternal = false;

        if ($documentId) {
            $document = Document::findOrFail($documentId);
            $this->assertDocumentInProject($document);
            $this->uploadCategory = $document->category->value;
            $this->uploadIsInternal = $document->is_internal;
        }

        $this->dispatch('open-modal', 'document-upload-modal');
    }

    public function closeUploadModal(): void
    {
        $this->dispatch('close-modal', 'document-upload-modal');
        $this->uploadDocumentId = null;
    }

    /**
     * What the browser uploader needs to know. Everything here is re-checked
     * server side — this only shapes the client's behaviour.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function uploadConfig(): array
    {
        return [
            'projectId' => $this->contextProject()->id,
            'jobSiteId' => $this->activeJobSiteId(),
            'folderId' => $this->currentFolder?->id,
            'documentId' => $this->uploadDocumentId,
            'mode' => DocumentSettings::isCloudConfigured() ? 'cloud' : 'local',
            'maxBytes' => DocumentSettings::maxUploadBytes(),
            'maxLabel' => DocumentSettings::formatBytes(DocumentSettings::maxUploadBytes()),
            'accept' => DocumentSettings::acceptAttribute(),
            'partSize' => DocumentSettings::partSize(),
            'endpoints' => [
                'init' => route('documents.uploads.init'),
                'parts' => route('documents.uploads.parts'),
                'complete' => route('documents.uploads.complete'),
                'abort' => route('documents.uploads.abort'),
            ],
        ];
    }

    /**
     * Changes whenever the uploader's target changes, so the Alpine component
     * is rebuilt rather than left pointing at the previous folder.
     */
    public function uploadContextKey(): string
    {
        return sprintf('%s-%s-%s', $this->activeJobSiteId() ?? 'p', $this->folderId ?? 'root', $this->uploadDocumentId ?? 'new');
    }

    /**
     * Called by the uploader once a file is stored, so the list and the tags
     * catch up. Tags and the location cannot be set by the browser — they are
     * applied here, where the guards are.
     */
    public function documentUploaded(int $documentId): void
    {
        $this->authorizeDocumentWrite();

        $document = Document::find($documentId);

        if (! $document) {
            return;
        }

        $this->assertDocumentInProject($document);

        if (! $this->uploadDocumentId) {
            $document->update([
                'category' => DocumentCategory::tryFrom($this->uploadCategory) ?? DocumentCategory::OTHER,
                'is_internal' => $this->uploadIsInternal,
            ]);

            $this->syncTags($document, $this->uploadTags);
        }

        unset($this->documents, $this->stats, $this->folders, $this->availableTags);
    }

    /**
     * The upload path for installs without Cloudflare R2: the file travels
     * through PHP, so it is bounded by upload_max_filesize / post_max_size,
     * and the panel says so before the user picks a 2 GB drawing set.
     */
    public function saveLocalUploads(): void
    {
        $this->authorizeDocumentWrite();

        $maxKilobytes = (int) max(1, floor(DocumentSettings::maxUploadBytes() / 1024));

        $this->validate([
            'localUploads' => ['required', 'array', 'max:20'],
            'localUploads.*' => ['file', 'max:'.$maxKilobytes],
        ], [
            'localUploads.required' => __('Choose at least one file to upload.'),
            'localUploads.*.max' => __('Each file must be :size or smaller.', [
                'size' => DocumentSettings::formatBytes(DocumentSettings::maxUploadBytes()),
            ]),
        ]);

        if ($this->isFlatMode() && ! $this->uploadDocumentId) {
            $this->addError('localUploads', __('Choose a location before uploading.'));

            return;
        }

        if (! DocumentCategory::tryFrom($this->uploadCategory)) {
            $this->uploadCategory = DocumentCategory::OTHER->value;
        }

        $incoming = collect($this->localUploads)->sum(fn ($file) => (int) $file->getSize());

        if (DocumentSettings::wouldExceedQuota($incoming)) {
            $this->addError('localUploads', __('This install has reached its storage limit of :size.', [
                'size' => DocumentSettings::formatBytes(DocumentSettings::storageQuotaBytes()),
            ]));

            return;
        }

        $storage = app(DocumentStorageService::class);
        $stored = 0;

        foreach ($this->localUploads as $upload) {
            $originalName = $upload->getClientOriginalName();

            if (! DocumentSettings::isAllowedFile($originalName, $upload->getMimeType())) {
                $this->addError('localUploads', __(':file is not an allowed file type.', ['file' => $originalName]));

                continue;
            }

            DB::transaction(function () use ($storage, $upload, $originalName, &$stored) {
                $document = $this->uploadDocumentId
                    ? Document::findOrFail($this->uploadDocumentId)
                    : Document::create([
                        'project_id' => $this->contextProject()->id,
                        'job_site_id' => $this->activeJobSiteId(),
                        'folder_id' => $this->currentFolder?->id,
                        'name' => DocumentSettings::sanitizeFileName($originalName),
                        'category' => $this->uploadCategory,
                        'is_internal' => $this->uploadIsInternal,
                        'uploaded_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);

                if ($this->uploadDocumentId) {
                    $this->assertDocumentInProject($document);
                }

                $plan = $storage->beginUpload(
                    $document,
                    $originalName,
                    (int) $upload->getSize(),
                    $upload->getMimeType()
                );

                $version = \App\Models\DocumentVersion::findOrFail($plan['version_id']);

                if (filled($this->uploadVersionNotes)) {
                    $version->update(['notes' => $this->uploadVersionNotes]);
                }

                $version = $storage->storeLocalUpload($version, $upload);

                $document->update([
                    'current_version_id' => $version->id,
                    'current_size_bytes' => $version->size_bytes,
                    'current_mime_type' => $version->mime_type,
                    'current_version_number' => $version->version_number,
                    'updated_by' => auth()->id(),
                ]);

                DocumentActivity::record(
                    $version->version_number === 1 ? DocumentActivity::UPLOADED : DocumentActivity::VERSION_ADDED,
                    ['document_id' => $document->id],
                    ['version' => $version->version_number, 'size' => $version->size_bytes]
                );

                if (! $this->uploadDocumentId && filled($this->uploadTags)) {
                    $this->syncTags($document, $this->uploadTags);
                }

                $stored++;
            });
        }

        $this->localUploads = [];

        if ($stored > 0) {
            $this->closeUploadModal();

            session()->flash('message', trans_choice(
                '{1} :count file uploaded.|[2,*] :count files uploaded.',
                $stored,
                ['count' => $stored]
            ));
        }

        unset($this->documents, $this->stats, $this->folders, $this->availableTags);
    }

    // =========================================================================
    // DOCUMENT EDITING
    // =========================================================================

    public function openEditModal(int $documentId): void
    {
        $this->authorizeDocumentWrite();

        $document = Document::with('tags')->findOrFail($documentId);
        $this->assertDocumentInProject($document);

        $this->editingDocumentId = $document->id;
        $this->documentName = $document->name;
        $this->documentDescription = (string) $document->description;
        $this->documentCategory = $document->category->value;
        $this->documentTags = $document->tags->pluck('name')->implode(', ');
        $this->documentIsInternal = $document->is_internal;
        $this->documentFolderId = (string) ($document->folder_id ?? '');
        $this->documentJobSiteId = (string) ($document->job_site_id ?? '');
        $this->dispatch('open-modal', 'document-edit-modal');
    }

    public function saveDocument(): void
    {
        $this->authorizeDocumentWrite();

        $this->validate([
            'documentName' => ['required', 'string', 'max:255'],
            'documentDescription' => ['nullable', 'string', 'max:2000'],
            'documentCategory' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(DocumentCategory::options()))],
            'documentTags' => ['nullable', 'string', 'max:500'],
        ], [], [
            'documentName' => __('name'),
            'documentCategory' => __('category'),
        ]);

        $document = Document::findOrFail($this->editingDocumentId);
        $this->assertDocumentInProject($document);

        $before = [
            'name' => $document->name,
            'folder_id' => $document->folder_id,
            'job_site_id' => $document->job_site_id,
            'category' => $document->category->value,
        ];

        $jobSiteId = $this->isJobSiteContext()
            ? $document->job_site_id
            : ($this->documentJobSiteId === '' ? null : (int) $this->documentJobSiteId);

        if ($jobSiteId) {
            $this->assertJobSiteInProject($jobSiteId);
        }

        $folderId = $this->documentFolderId === '' ? null : (int) $this->documentFolderId;

        if ($folderId) {
            $folder = DocumentFolder::findOrFail($folderId);

            // A folder belongs to one location; moving a document to another
            // location cannot leave it in a folder that does not exist there.
            if ($folder->project_id !== $document->project_id || $folder->job_site_id !== $jobSiteId) {
                $this->addError('documentFolderId', __('That folder belongs to a different location.'));

                return;
            }
        }

        $document->update([
            'name' => DocumentSettings::sanitizeFileName($this->documentName),
            'description' => $this->documentDescription ?: null,
            'category' => $this->documentCategory,
            'is_internal' => $this->documentIsInternal,
            'folder_id' => $folderId,
            'job_site_id' => $jobSiteId,
            'updated_by' => auth()->id(),
        ]);

        $this->syncTags($document, $this->documentTags);

        if ($before['name'] !== $document->name) {
            DocumentActivity::record(DocumentActivity::RENAMED, ['document_id' => $document->id], [
                'from' => $before['name'],
                'to' => $document->name,
            ]);
        }

        if ($before['folder_id'] !== $document->folder_id || $before['job_site_id'] !== $document->job_site_id) {
            DocumentActivity::record(DocumentActivity::MOVED, ['document_id' => $document->id], [
                'folder' => $document->folder?->name,
                'location' => $document->locationLabel(),
            ]);
        }

        if ($before['category'] !== $document->category->value) {
            DocumentActivity::record(DocumentActivity::RECATEGORISED, ['document_id' => $document->id], [
                'from' => $before['category'],
                'to' => $document->category->value,
            ]);
        }

        $this->closeEditModal();

        session()->flash('message', __('Document updated.'));
    }

    public function closeEditModal(): void
    {
        $this->dispatch('close-modal', 'document-edit-modal');
        $this->editingDocumentId = null;
        $this->resetValidation();
    }

    // =========================================================================
    // DETAIL VIEW
    // =========================================================================

    public function openDetail(int $documentId): void
    {
        $document = Document::withTrashed()->findOrFail($documentId);

        $this->assertDocumentInProject($document);

        $this->viewingDocumentId = $document->id;

        $this->dispatch('open-modal', 'document-detail-modal');
    }

    public function closeDetail(): void
    {
        $this->viewingDocumentId = null;

        $this->dispatch('close-modal', 'document-detail-modal');
    }

    /**
     * Everything the detail view shows, loaded in one go.
     */
    #[Computed]
    public function viewingDocument(): ?Document
    {
        if (! $this->viewingDocumentId) {
            return null;
        }

        $document = Document::withTrashed()
            ->with([
                'versions.uploadedBy',
                'tags',
                'folder.parent',
                'jobSite',
                'project',
                'uploadedBy',
                'updatedBy',
                'deletedBy',
                'shares.createdBy',
                'activities.user',
            ])
            ->find($this->viewingDocumentId);

        if (! $document || ! $document->isVisibleTo(auth()->user())) {
            return null;
        }

        return $document;
    }

    /**
     * Bring an older version back as the current one.
     *
     * The old version is not rewound to and nothing is overwritten: the file
     * is copied to a new version at the top of the history, so the trail of
     * what happened stays complete.
     */
    public function restoreVersion(int $versionId): void
    {
        $this->authorizeDocumentWrite();

        $version = DocumentVersion::findOrFail($versionId);
        $document = Document::withTrashed()->find($version->document_id);

        abort_unless($document, 404);

        $this->assertDocumentInProject($document);

        abort_if($document->trashed(), 409, 'Restore the document before changing its versions.');

        abort_unless($version->isAvailable(), 409, 'That version was never uploaded successfully.');

        if ($version->id === $document->current_version_id) {
            session()->flash('message', __('That version is already the current one.'));

            return;
        }

        $storage = app(DocumentStorageService::class);
        $number = (int) $document->versions()->max('version_number') + 1;

        $key = DocumentSettings::objectKey(
            $document->project_id,
            $document->uuid,
            $number,
            $version->original_name
        );

        try {
            $storage->copyObject($version, $key);
        } catch (\Throwable $e) {
            report($e);

            session()->flash('error', __('That version could not be restored: its file could not be copied.'));

            return;
        }

        $restored = $document->versions()->create([
            'version_number' => $number,
            'disk' => $version->disk,
            'object_key' => $key,
            'original_name' => $version->original_name,
            'size_bytes' => $version->size_bytes,
            'mime_type' => $version->mime_type,
            'checksum' => $version->checksum,
            'notes' => __('Restored from version :number', ['number' => $version->version_number]),
            'upload_status' => DocumentVersion::STATUS_AVAILABLE,
            'uploaded_by' => auth()->id(),
        ]);

        $document->update([
            'current_version_id' => $restored->id,
            'current_size_bytes' => $restored->size_bytes,
            'current_mime_type' => $restored->mime_type,
            'current_version_number' => $restored->version_number,
            'updated_by' => auth()->id(),
        ]);

        DocumentActivity::record(DocumentActivity::VERSION_ADDED, ['document_id' => $document->id], [
            'version' => $restored->version_number,
            'restored_from' => $version->version_number,
        ]);

        unset($this->viewingDocument, $this->documents, $this->stats);

        session()->flash('message', __('Version :number restored as version :new.', [
            'number' => $version->version_number,
            'new' => $restored->version_number,
        ]));
    }

    // =========================================================================
    // SHARE LINKS
    // =========================================================================

    /**
     * Open the share panel for one document or one folder.
     */
    public function openShareModal(?int $documentId = null, ?int $folderId = null): void
    {
        $this->authorizeDocumentWrite();

        if ($documentId) {
            $document = Document::findOrFail($documentId);
            $this->assertDocumentInProject($document);
        }

        if ($folderId) {
            $folder = DocumentFolder::findOrFail($folderId);
            $this->assertFolderInLocation($folder);
        }

        abort_unless($documentId || $folderId, 400);

        $this->sharingDocumentId = $documentId;
        $this->sharingFolderId = $folderId;
        $this->createdShareUrl = null;
        $this->resetValidation();

        $this->shareExpiresAt = now()->addDays((int) config('documents.share_default_days', 14))->format('Y-m-d');
        $this->sharePassword = '';
        $this->shareAllowDownload = true;
        $this->shareMaxDownloads = '';
        $this->shareRecipient = '';

        $this->dispatch('open-modal', 'document-share-modal');
    }

    public function closeShareModal(): void
    {
        $this->sharingDocumentId = null;
        $this->sharingFolderId = null;
        $this->createdShareUrl = null;
        $this->resetValidation();

        $this->dispatch('close-modal', 'document-share-modal');
    }

    public function createShare(): void
    {
        $this->authorizeDocumentWrite();

        $this->validate([
            'shareExpiresAt' => ['nullable', 'date', 'after:today'],
            'sharePassword' => ['nullable', 'string', 'min:4', 'max:100'],
            'shareMaxDownloads' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'shareRecipient' => ['nullable', 'string', 'max:120'],
        ], [
            'shareExpiresAt.after' => __('The expiry date must be in the future.'),
            'sharePassword.min' => __('The password must be at least 4 characters.'),
        ], [
            'shareExpiresAt' => __('expiry date'),
            'sharePassword' => __('password'),
            'shareMaxDownloads' => __('download limit'),
        ]);

        // Re-check the target: the ids have been sitting in the browser since
        // the panel was opened.
        if ($this->sharingDocumentId) {
            $document = Document::findOrFail($this->sharingDocumentId);
            $this->assertDocumentInProject($document);
        }

        if ($this->sharingFolderId) {
            $folder = DocumentFolder::findOrFail($this->sharingFolderId);
            $this->assertFolderInLocation($folder);
        }

        abort_unless($this->sharingDocumentId || $this->sharingFolderId, 400);

        $share = DocumentShare::create([
            'document_id' => $this->sharingDocumentId,
            'folder_id' => $this->sharingFolderId,
            'recipient_label' => $this->shareRecipient ?: null,
            'expires_at' => $this->shareExpiresAt ? \Carbon\Carbon::parse($this->shareExpiresAt)->endOfDay() : null,
            'password_hash' => $this->sharePassword ? \Illuminate\Support\Facades\Hash::make($this->sharePassword) : null,
            'allow_download' => $this->shareAllowDownload,
            'max_downloads' => $this->shareMaxDownloads !== '' ? (int) $this->shareMaxDownloads : null,
            'created_by' => auth()->id(),
        ]);

        DocumentActivity::record(
            DocumentActivity::SHARED,
            [
                'document_id' => $this->sharingDocumentId,
                'folder_id' => $this->sharingFolderId,
                'share_id' => $share->id,
            ],
            [
                'recipient' => $share->recipient_label,
                'expires_at' => $share->expires_at?->toDateString(),
                'password' => $share->requiresPassword(),
            ]
        );

        $this->createdShareUrl = $share->publicUrl();
        $this->sharePassword = '';

        unset($this->shares, $this->viewingDocument);
    }

    public function revokeShare(int $shareId): void
    {
        $this->authorizeDocumentWrite();

        // withTrashed: a link to a deleted document must still be revocable,
        // which is exactly when someone is most likely to want it gone.
        $share = DocumentShare::with(['documentWithTrashed', 'folderWithTrashed'])->findOrFail($shareId);
        $document = $share->documentWithTrashed;
        $folder = $share->folderWithTrashed;

        if ($document) {
            abort_unless($document->project_id === $this->contextProject()->id, 403);

            if ($jobSite = $this->contextJobSite()) {
                abort_unless($document->job_site_id === $jobSite->id, 403);
            }
        } elseif ($folder) {
            abort_unless($folder->project_id === $this->contextProject()->id, 403);
        } else {
            abort(404);
        }

        $share->update(['revoked_at' => now(), 'revoked_by' => auth()->id()]);

        DocumentActivity::record(
            DocumentActivity::SHARE_REVOKED,
            [
                'document_id' => $share->document_id,
                'folder_id' => $share->folder_id,
                'share_id' => $share->id,
            ],
            ['recipient' => $share->recipient_label]
        );

        unset($this->shares, $this->viewingDocument);

        session()->flash('message', __('Share link revoked. It stops working immediately.'));
    }

    /**
     * Kill every live link to something being deleted. The model refuses a
     * link whose target is gone anyway; this makes it explicit, auditable, and
     * visible in the share list rather than only implied.
     */
    protected function revokeSharesFor(Document|DocumentFolder $target): int
    {
        $query = $target instanceof Document
            ? DocumentShare::where('document_id', $target->id)
            : DocumentShare::where('folder_id', $target->id);

        return $query->whereNull('revoked_at')->update([
            'revoked_at' => now(),
            'revoked_by' => auth()->id(),
        ]);
    }

    /**
     * Links already made for whatever the share panel is pointed at.
     *
     * @return Collection<int, DocumentShare>
     */
    #[Computed]
    public function shares(): Collection
    {
        if (! $this->sharingDocumentId && ! $this->sharingFolderId) {
            return new Collection();
        }

        return DocumentShare::query()
            ->when($this->sharingDocumentId, fn (Builder $q) => $q->where('document_id', $this->sharingDocumentId))
            ->when($this->sharingFolderId, fn (Builder $q) => $q->where('folder_id', $this->sharingFolderId))
            ->with('createdBy')
            ->latest()
            ->get();
    }

    /**
     * What is being shared, for the panel heading.
     */
    public function shareTargetName(): string
    {
        if ($this->sharingDocumentId) {
            return Document::find($this->sharingDocumentId)?->name ?? '';
        }

        if ($this->sharingFolderId) {
            return DocumentFolder::find($this->sharingFolderId)?->name ?? '';
        }

        return '';
    }

    // =========================================================================
    // DELETE / RESTORE
    // =========================================================================

    public function deleteDocument(int $documentId): void
    {
        $this->authorizeDocumentDelete();

        $document = Document::findOrFail($documentId);
        $this->assertDocumentInProject($document);

        $document->update(['deleted_by' => auth()->id()]);
        $document->delete();

        $revoked = $this->revokeSharesFor($document);

        DocumentActivity::record(DocumentActivity::DELETED, ['document_id' => $document->id], [
            'name' => $document->name,
            'shares_revoked' => $revoked,
        ]);

        $this->selected = array_values(array_diff($this->selected, [$documentId]));

        if ($this->viewingDocumentId === $documentId) {
            $this->closeDetail();
        }

        session()->flash('message', $this->retentionMessage());
    }

    public function restoreDocument(int $documentId): void
    {
        $this->authorizeDocumentDelete();

        $document = Document::onlyTrashed()->findOrFail($documentId);
        $this->assertDocumentInProject($document);

        $document->restore();
        $document->update(['deleted_by' => null]);

        DocumentActivity::record(DocumentActivity::RESTORED, ['document_id' => $document->id], [
            'name' => $document->name,
        ]);

        session()->flash('message', __('Document restored.'));
    }

    /**
     * Remove a trashed document and its stored files for good.
     */
    public function purgeDocument(int $documentId): void
    {
        $this->authorizeDocumentDelete();

        $document = Document::onlyTrashed()->with('versions')->findOrFail($documentId);
        $this->assertDocumentInProject($document);

        $this->purgeDocuments(new Collection([$document]));

        $this->selected = array_values(array_diff($this->selected, [$documentId]));

        if ($this->viewingDocumentId === $documentId) {
            $this->closeDetail();
        }

        session()->flash('message', __('Document permanently deleted.'));
    }

    // =========================================================================
    // BULK ACTIONS
    // =========================================================================

    public function toggleSelectAll(): void
    {
        $ids = $this->documents->pluck('id')->all();

        $this->selected = count(array_intersect($this->selected, $ids)) === count($ids)
            ? []
            : $ids;
    }

    public function bulkMove(): void
    {
        $this->authorizeDocumentWrite();

        if (! $this->selected) {
            return;
        }

        $folderId = $this->bulkFolderId === '' ? null : (int) $this->bulkFolderId;
        $folder = null;

        if ($folderId) {
            $folder = DocumentFolder::findOrFail($folderId);
            $this->assertFolderInLocation($folder);
        }

        $documents = $this->selectedDocuments();

        foreach ($documents as $document) {
            $document->update([
                'folder_id' => $folderId,
                'job_site_id' => $folder ? $folder->job_site_id : $document->job_site_id,
                'updated_by' => auth()->id(),
            ]);

            DocumentActivity::record(DocumentActivity::MOVED, ['document_id' => $document->id], [
                'folder' => $folder?->name,
            ]);
        }

        $this->selected = [];
        $this->bulkFolderId = '';

        session()->flash('message', trans_choice('{1} :count document moved.|[2,*] :count documents moved.', $documents->count(), ['count' => $documents->count()]));
    }

    public function bulkSetCategory(): void
    {
        $this->authorizeDocumentWrite();

        if (! $this->selected || $this->bulkCategory === '') {
            return;
        }

        if (! DocumentCategory::tryFrom($this->bulkCategory)) {
            return;
        }

        $documents = $this->selectedDocuments();

        foreach ($documents as $document) {
            $document->update([
                'category' => $this->bulkCategory,
                'updated_by' => auth()->id(),
            ]);

            DocumentActivity::record(DocumentActivity::RECATEGORISED, ['document_id' => $document->id], [
                'to' => $this->bulkCategory,
            ]);
        }

        $this->selected = [];
        $this->bulkCategory = '';

        session()->flash('message', trans_choice('{1} :count document updated.|[2,*] :count documents updated.', $documents->count(), ['count' => $documents->count()]));
    }

    /**
     * Bring every selected document back out of the trash.
     */
    public function bulkRestore(): void
    {
        $this->authorizeDocumentDelete();

        if (! $this->selected) {
            return;
        }

        $documents = $this->selectedDocuments(trashed: true);

        foreach ($documents as $document) {
            $document->restore();
            $document->update(['deleted_by' => null]);

            DocumentActivity::record(DocumentActivity::RESTORED, ['document_id' => $document->id], [
                'name' => $document->name,
            ]);
        }

        $count = $documents->count();
        $this->selected = [];

        session()->flash('message', trans_choice(
            '{1} :count document restored.|[2,*] :count documents restored.',
            $count,
            ['count' => $count]
        ));
    }

    /**
     * Delete the selected documents from the trash for good, files included.
     */
    public function bulkPurge(): void
    {
        $this->authorizeDocumentDelete();

        if (! $this->selected) {
            return;
        }

        $count = $this->purgeDocuments($this->selectedDocuments(trashed: true)->load('versions'));

        $this->selected = [];

        session()->flash('message', trans_choice(
            '{1} :count document permanently deleted.|[2,*] :count documents permanently deleted.',
            $count,
            ['count' => $count]
        ));
    }

    /**
     * Empty the trash. Deliberately scoped to what the screen is showing —
     * the current location and the current filters — so the button never
     * removes something the user cannot see.
     */
    public function emptyTrash(): void
    {
        $this->authorizeDocumentDelete();

        if (! $this->showTrash) {
            return;
        }

        $purged = 0;

        // Chunked: a long-neglected trash can hold a great many rows, and each
        // one means a call out to storage.
        $this->documentQuery()->with('versions')->chunkById(100, function (Collection $chunk) use (&$purged) {
            $purged += $this->purgeDocuments($chunk);
        });

        $this->selected = [];

        unset($this->documents, $this->stats, $this->trashCount);

        session()->flash('message', trans_choice(
            '{0} The trash was already empty.|{1} :count document permanently deleted.|[2,*] :count documents permanently deleted.',
            $purged,
            ['count' => $purged]
        ));
    }

    public function bulkDelete(): void
    {
        $this->authorizeDocumentDelete();

        if (! $this->selected) {
            return;
        }

        $documents = $this->selectedDocuments();

        foreach ($documents as $document) {
            $document->update(['deleted_by' => auth()->id()]);
            $document->delete();

            $revoked = $this->revokeSharesFor($document);

            DocumentActivity::record(DocumentActivity::DELETED, ['document_id' => $document->id], [
                'name' => $document->name,
                'shares_revoked' => $revoked,
            ]);
        }

        $count = $documents->count();
        $this->selected = [];

        session()->flash('message', trans_choice('{1} :count document moved to the trash.|[2,*] :count documents moved to the trash.', $count, ['count' => $count]));
    }

    // =========================================================================
    // DATA FOR THE VIEW
    // =========================================================================

    /**
     * The folders directly inside the folder being browsed.
     *
     * @return Collection<int, DocumentFolder>
     */
    #[Computed]
    public function folders(): Collection
    {
        if ($this->isFlatMode() || $this->showTrash) {
            return new Collection();
        }

        return DocumentFolder::forLocation($this->contextProject()->id, $this->activeJobSiteId())
            ->where('parent_id', $this->folderId)
            ->withCount(['documents' => fn (Builder $q) => $q->whereNotNull('current_version_id')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Every folder of the active location, for the move and upload pickers.
     *
     * @return Collection<int, DocumentFolder>
     */
    #[Computed]
    public function folderOptions(): Collection
    {
        $jobSiteId = $this->isFlatMode() && $this->editingDocumentId
            ? (($this->documentJobSiteId === '') ? null : (int) $this->documentJobSiteId)
            : $this->activeJobSiteId();

        return DocumentFolder::forLocation($this->contextProject()->id, $jobSiteId)
            ->with('parent')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function currentFolder(): ?DocumentFolder
    {
        if (! $this->folderId) {
            return null;
        }

        $folder = DocumentFolder::find($this->folderId);

        // Same check as bootedManagesDocuments(), because a computed property
        // can be reached in a request where the boot hook has already run and
        // the value changed since.
        if (! $folder
            || $folder->project_id !== $this->contextProject()->id
            || $folder->job_site_id !== $this->activeJobSiteId()) {
            return null;
        }

        return $folder;
    }

    /**
     * The folder path of the current view, outermost first.
     *
     * @return BaseCollection<int, DocumentFolder>
     */
    #[Computed]
    public function breadcrumb(): BaseCollection
    {
        $folder = $this->currentFolder;

        if (! $folder) {
            return new BaseCollection();
        }

        return $folder->ancestors()->push($folder);
    }

    #[Computed]
    public function documents()
    {
        return $this->documentQuery()
            ->with(['currentVersion', 'folder', 'jobSite', 'tags', 'uploadedBy'])
            ->orderByDesc('updated_at')
            ->paginate(25);
    }

    /**
     * Count, total size and the category spread of everything the current
     * filters match — not just the page on screen.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function stats(): array
    {
        $base = $this->documentQuery();

        $totals = (clone $base)
            ->selectRaw('COUNT(*) as document_count, COALESCE(SUM(current_size_bytes), 0) as total_bytes')
            ->first();

        $byCategory = (clone $base)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        $quota = DocumentSettings::storageQuotaBytes();

        // Two different numbers, and they must not be confused: what this
        // project holds, and what the whole install holds. The quota is an
        // install-wide ceiling (config/documents.php), so the bar measures the
        // install — ten projects at 40% each are 400% of one limit.
        $projectBytes = (int) Document::where('project_id', $this->contextProject()->id)
            ->sum('current_size_bytes');

        $installBytes = $quota ? DocumentSettings::installUsedBytes() : $projectBytes;

        return [
            'count' => (int) ($totals->document_count ?? 0),
            'bytes' => (int) ($totals->total_bytes ?? 0),
            'size' => DocumentSettings::formatBytes((int) ($totals->total_bytes ?? 0)),
            'by_category' => $byCategory,
            'project_bytes' => $projectBytes,
            'project_size' => DocumentSettings::formatBytes($projectBytes),
            'install_size' => DocumentSettings::formatBytes($installBytes),
            'quota' => $quota,
            'quota_size' => $quota ? DocumentSettings::formatBytes($quota) : null,
            'quota_percent' => $quota ? min(100, (int) round($installBytes / max($quota, 1) * 100)) : null,
        ];
    }

    /**
     * @return Collection<int, DocumentTag>
     */
    #[Computed]
    public function availableTags(): Collection
    {
        return DocumentTag::orderBy('name')->get();
    }

    /**
     * People who have uploaded into this project, for the uploader filter.
     */
    #[Computed]
    public function uploaders()
    {
        return \App\Models\User::whereIn(
            'id',
            Document::where('project_id', $this->contextProject()->id)
                ->whereNotNull('uploaded_by')
                ->distinct()
                ->pluck('uploaded_by')
        )->orderBy('name')->get();
    }

    #[Computed]
    public function categories(): array
    {
        return DocumentCategory::options();
    }

    #[Computed]
    public function trashCount(): int
    {
        return Document::onlyTrashed()
            ->where('project_id', $this->contextProject()->id)
            ->when($this->isJobSiteContext(), fn (Builder $q) => $q->where('job_site_id', $this->contextJobSite()->id))
            ->count();
    }

    public function canUploadHere(): bool
    {
        return $this->canManageDocuments() && ! $this->showTrash;
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * The filtered query behind the list, the stats and the bulk actions.
     */
    protected function documentQuery(): Builder
    {
        $projectId = $this->contextProject()->id;

        $query = Document::query()
            ->where('project_id', $projectId)
            ->visibleTo(auth()->user())
            ->ready();

        if ($this->showTrash) {
            $query->onlyTrashed();
        }

        // Location
        if ($this->isJobSiteContext()) {
            $query->where('job_site_id', $this->contextJobSite()->id);
        } elseif ($this->locationFilter === 'project') {
            $query->whereNull('job_site_id');
        } elseif (is_numeric($this->locationFilter)) {
            $query->where('job_site_id', (int) $this->locationFilter);
        }

        // Folder — only meaningful when a tree is on screen. Searching looks
        // through the whole location rather than the open folder, because a
        // user searching has stopped browsing.
        if (! $this->isFlatMode() && ! $this->showTrash && blank($this->search)) {
            $query->where('folder_id', $this->folderId);
        }

        if (filled($this->search)) {
            $term = '%'.$this->search.'%';

            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $term));
            });
        }

        if (filled($this->categoryFilter)) {
            $query->where('category', $this->categoryFilter);
        }

        if (filled($this->tagFilter)) {
            $query->whereHas('tags', fn (Builder $q) => $q->whereKey($this->tagFilter));
        }

        if (filled($this->uploaderFilter)) {
            $query->where('uploaded_by', $this->uploaderFilter);
        }

        if (filled($this->dateFrom)) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if (filled($this->dateTo)) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $query;
    }

    /**
     * @return Collection<int, Document>
     */
    protected function selectedDocuments(bool $trashed = false): Collection
    {
        return Document::query()
            ->when($trashed, fn (Builder $q) => $q->onlyTrashed())
            ->whereIn('id', $this->selected)
            ->where('project_id', $this->contextProject()->id)
            // The job site page must not reach a project-level document, even
            // if its id is put into the selection by hand.
            ->when($this->isJobSiteContext(), fn (Builder $q) => $q->where('job_site_id', $this->contextJobSite()->id))
            ->visibleTo(auth()->user())
            ->get();
    }

    /**
     * Remove documents and the files behind them for good. Used by the single
     * purge, the bulk purge and Empty Trash, so all three behave identically.
     *
     * @param  Collection<int, Document>  $documents
     */
    protected function purgeDocuments(Collection $documents): int
    {
        $storage = app(DocumentStorageService::class);

        foreach ($documents as $document) {
            foreach ($document->versions as $version) {
                $storage->deleteObject($version);
            }

            // Recorded without the document id: the row is about to go, and
            // its activity rows go with it.
            DocumentActivity::record(DocumentActivity::PURGED, ['document_id' => null], [
                'name' => $document->name,
                'project_id' => $document->project_id,
            ]);

            $document->forceDelete();
        }

        return $documents->count();
    }

    /**
     * Turn "as built, aprovado" into tag rows and attach exactly those.
     */
    protected function syncTags(Document $document, string $input): void
    {
        $names = collect(explode(',', $input))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->take(15);

        $ids = $names
            ->map(fn (string $name) => DocumentTag::findOrCreateByName($name, auth()->id())->id)
            ->all();

        $changes = $document->tags()->sync($ids);

        // Only worth a history entry when the tags actually moved.
        if ($changes['attached'] || $changes['detached']) {
            DocumentActivity::record(DocumentActivity::TAGGED, ['document_id' => $document->id], [
                'tags' => $names->values()->all(),
            ]);
        }
    }

    protected function retentionMessage(): string
    {
        $days = config('documents.retention_days');

        return $days
            ? __('Document moved to the trash. It can be restored for :days days.', ['days' => $days])
            : __('Document moved to the trash.');
    }

    /**
     * An id from the browser must never reach another project's data.
     */
    protected function assertDocumentInProject(Document $document): void
    {
        abort_unless($document->project_id === $this->contextProject()->id, 403);

        if ($jobSite = $this->contextJobSite()) {
            abort_unless($document->job_site_id === $jobSite->id, 403);
        }

        abort_unless($document->isVisibleTo(auth()->user()), 403);
    }

    protected function assertFolderInLocation(DocumentFolder $folder): void
    {
        abort_unless($folder->project_id === $this->contextProject()->id, 403);
        abort_unless($folder->job_site_id === $this->activeJobSiteId(), 403);
    }

    protected function assertJobSiteInProject(int $jobSiteId): void
    {
        abort_unless(
            JobSite::whereKey($jobSiteId)->where('project_id', $this->contextProject()->id)->exists(),
            403
        );
    }
}
