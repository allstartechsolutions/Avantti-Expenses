<?php

namespace App\Livewire\Subcontractor;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\DocumentType;
use App\Models\FileUpload;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use App\Models\SubcontractorEmployee;
use App\Models\Vendor;
use App\Services\DocumentSettings;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubcontractorShow extends Component
{
    use AuthorizesAbility;

    use WithFileUploads;

    public Subcontractor $subcontractor;
    public string $activeTab = 'overview';

    // Document upload form
    public $document_type_id = '';
    public $document_file;
    public $expiration_date = '';
    public $document_notes = '';
    public bool $showUploadForm = false;

    /** The document being renewed, when the upload form is a renewal rather than a first filing. */
    public ?int $renewing_document_id = null;

    /**
     * The file already in storage, waiting for the dialog to be saved. It is
     * attached to the vendor until then, and moved onto the document row on
     * save; cancelling the dialog removes it again.
     */
    public ?int $pending_file_id = null;

    // Archive dialog
    public ?int $archiving_document_id = null;
    public string $archive_reason = '';

    // History dialog: one document's chain — what it replaced, what replaced it
    public ?int $history_document_id = null;

    // Employee form
    public $employee_title = '';
    public $employee_name = '';
    public $employee_phone = '';
    public $employee_email = '';
    public $employee_notes = '';
    public bool $showEmployeeForm = false;

    // Delete modal
    public $showDeleteModal = false;


    /**
     * Only the names that differ from the shared map in
     * lang/<locale>/validation.php — everything else falls through to it.
     */
    public function validationAttributes(): array
    {
        return [
            'employee_email' => __('email'),
        ];
    }

    protected function rules()
    {
        // A renewal keeps the type of the document it replaces even when that
        // type has since been retired — otherwise a lapsing certificate under
        // a retired type could never be renewed, only deleted. A first filing
        // may only use a live type.
        // With a bucket the file has already gone up through the uploader and
        // `pending_file_id` says which; without one it comes through Livewire
        // and is stored on save. Only the second needs a rule here.
        $rules = [
            'document_type_id' => ['required', $this->renewing_document_id
                ? Rule::exists('document_types', 'id')
                : Rule::exists('document_types', 'id')->where('is_active', true)],
            'document_file' => DocumentSettings::isCloudConfigured()
                ? 'nullable'
                : 'required|file|max:'.(int) floor(app(FileUploadService::class)->maxBytes() / 1024),
            'document_notes' => 'nullable|string|max:500',
        ];

        // Check if selected document type requires expiration
        if ($this->document_type_id) {
            $docType = DocumentType::find($this->document_type_id);
            if ($docType && $docType->requires_expiration) {
                $rules['expiration_date'] = 'required|date|after:today';
            } else {
                $rules['expiration_date'] = 'nullable|date';
            }
        }

        return $rules;
    }


    public function mount(Subcontractor $subcontractor)
    {
        $this->authorizeAbility('vendors.view');

        $this->subcontractor = $subcontractor->load('createdBy');
    }

    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    /** Open the upload dialog for a first filing, optionally with the type already chosen. */
    public function startUpload(?int $documentTypeId = null)
    {
        $this->authorizeAbility('vendors.renew_documents');

        $this->resetUploadForm();
        $this->document_type_id = $documentTypeId ? (string) $documentTypeId : '';
        $this->showUploadForm = true;
    }

    public function cancelUploadForm()
    {
        $this->resetUploadForm();
        $this->showUploadForm = false;
    }

    /** The document a renewal is replacing, for the dialog's banner. */
    public function getRenewingDocumentProperty(): ?SubcontractorDocument
    {
        return $this->renewing_document_id
            ? $this->subcontractor->documents()->with('documentType')->find($this->renewing_document_id)
            : null;
    }

    public function resetUploadForm()
    {
        $this->document_type_id = '';
        $this->document_file = null;
        $this->expiration_date = '';
        $this->document_notes = '';
        $this->renewing_document_id = null;
        $this->abortPendingFile();
        $this->resetValidation([
            'document_type_id',
            'document_file',
            'expiration_date',
            'document_notes',
        ]);
    }

    /**
     * Called by the uploader once the bytes are in storage and verified. The
     * server attached the file to this vendor when the upload began; the
     * browser only says which row landed.
     */
    public function documentFileUploaded(int $fileId)
    {
        $this->authorizeAbility('vendors.renew_documents');

        $file = $this->vendorFile($fileId);

        abort_unless($file && $file->isAvailable(), 404);

        // One file per document: a second drop replaces the first.
        if ($this->pending_file_id && $this->pending_file_id !== $file->id) {
            $this->abortPendingFile();
        }

        $this->pending_file_id = $file->id;
        $this->resetValidation('document_file');
    }

    /** Take the uploaded file back off before the dialog is saved. */
    public function discardPendingFile()
    {
        $this->authorizeAbility('vendors.renew_documents');

        $this->abortPendingFile();
    }

    /** The file waiting on this vendor, if it is still there. */
    public function getPendingFileProperty(): ?FileUpload
    {
        return $this->pending_file_id ? $this->vendorFile($this->pending_file_id) : null;
    }

    /** A file attached to this vendor — not to another one, whatever id the browser sent. */
    protected function vendorFile(int $fileId): ?FileUpload
    {
        return FileUpload::query()
            ->where('attachable_type', Vendor::class)
            ->where('attachable_id', $this->subcontractor->id)
            ->find($fileId);
    }

    /**
     * Remove a file still waiting on the vendor; a file already moved onto a
     * document is left alone. Read fresh, never through the memoised
     * `pendingFile` property: after a save that property still holds the
     * file the save just moved, and aborting it would delete the document's
     * own file.
     */
    protected function abortPendingFile(): void
    {
        if ($this->pending_file_id && ($file = $this->vendorFile($this->pending_file_id))) {
            app(FileUploadService::class)->abort($file);
        }

        $this->pending_file_id = null;
    }

    /** Take the chosen document back off before it is stored. */
    public function clearDocumentFile()
    {
        // Livewire's own `_removeUpload()` deletes the temporary file; dropping
        // only the reference would leave it in livewire-tmp until the daily
        // sweep.
        $this->document_file?->delete();

        $this->document_file = null;
    }

    public function updatedDocumentTypeId($value)
    {
        // Reset expiration date when document type changes
        $this->expiration_date = '';
    }

    /**
     * Open the upload form as a renewal: same type, new file and date. On
     * save the document being renewed steps aside and points at the new one.
     */
    public function startRenewal(int $documentId)
    {
        $this->authorizeAbility('vendors.renew_documents');

        $document = $this->ownDocument($documentId);

        abort_unless($document->isActive(), 422, __('Only an active document can be renewed.'));

        $this->resetUploadForm();
        $this->renewing_document_id = $document->id;
        $this->document_type_id = (string) $document->document_type_id;
        $this->showUploadForm = true;
    }

    public function uploadDocument()
    {
        $this->authorizeAbility('vendors.renew_documents');

        $renewing = $this->renewing_document_id ? $this->ownDocument($this->renewing_document_id) : null;

        if ($renewing) {
            // A renewal keeps the type of the document it replaces, whatever
            // the browser sent.
            $this->document_type_id = (string) $renewing->document_type_id;
        }

        $this->validate();

        $uploads = app(FileUploadService::class);
        $vendor = Vendor::withoutGlobalScopes()->findOrFail($this->subcontractor->id);

        if (DocumentSettings::isCloudConfigured()) {
            $file = $this->pendingFile;

            if (! $file || ! $file->isAvailable()) {
                $this->addError('document_file', __('Drop the file first — it goes up before the document is saved.'));

                return;
            }
        } else {
            try {
                $file = $uploads->storeThroughPhp($vendor, $this->document_file);
            } catch (\RuntimeException $e) {
                $this->addError('document_file', $e->getMessage());

                return;
            }
        }

        DB::transaction(function () use ($renewing, $file) {
            $document = SubcontractorDocument::create([
                'subcontractor_id' => $this->subcontractor->id,
                'document_type_id' => $this->document_type_id,
                'file_upload_id' => $file->id,
                'file_name' => $file->original_name,
                'file_size' => $file->size_bytes,
                'expiration_date' => $this->expiration_date ?: null,
                'notes' => $this->document_notes ?: null,
                'uploaded_by' => Auth::id(),
            ]);

            // The file belongs to the document now, not to the vendor's
            // waiting room — which is also what stops the prune from
            // treating it as an orphan.
            $file->attachable()->associate($document)->save();

            $renewing?->supersedeWith($document);
        });

        $wasRenewal = $renewing !== null;

        $this->pending_file_id = null;
        $this->document_file?->delete();
        $this->document_file = null;
        $this->resetUploadForm();
        $this->showUploadForm = false;

        session()->flash('message', $wasRenewal
            ? __('Document renewed successfully!')
            : __('Document uploaded successfully!'));
    }

    public function showHistory(int $documentId)
    {
        // Nothing beyond the page's own grant: the history is the same
        // information the tab shows, only all of it at once.
        $this->history_document_id = $this->ownDocument($documentId)->id;
    }

    public function closeHistory()
    {
        $this->history_document_id = null;
    }

    /**
     * One document's chain, newest first: everything that replaced it,
     * itself, and everything it replaced. Two policies of the same type are
     * two chains; an archived document is the head of its own.
     *
     * @param  \Illuminate\Support\Collection<int, SubcontractorDocument>  $documents  every document of this vendor
     * @return \Illuminate\Support\Collection<int, SubcontractorDocument>
     */
    protected function chainOf(SubcontractorDocument $document, $documents)
    {
        $byId = $documents->keyBy('id');
        $predecessorOf = $documents->whereNotNull('superseded_by_id')->keyBy('superseded_by_id');

        // Walk up to the head of the chain, then down through every predecessor.
        $head = $document;
        $seen = [$head->id => true];

        while ($head->superseded_by_id && isset($byId[$head->superseded_by_id]) && ! isset($seen[$head->superseded_by_id])) {
            $head = $byId[$head->superseded_by_id];
            $seen[$head->id] = true;
        }

        $chain = collect([$head]);
        $current = $head;

        while (isset($predecessorOf[$current->id]) && ! $chain->contains('id', $predecessorOf[$current->id]->id)) {
            $current = $predecessorOf[$current->id];
            $chain->push($current);
        }

        return $chain;
    }

    public function startArchive(int $documentId)
    {
        $this->authorizeAbility('vendors.archive_documents');

        $document = $this->ownDocument($documentId);

        abort_unless($document->isActive(), 422, __('Only an active document can be archived.'));

        $this->archiving_document_id = $document->id;
        $this->archive_reason = '';
        $this->resetValidation('archive_reason');
    }

    public function cancelArchive()
    {
        $this->archiving_document_id = null;
        $this->archive_reason = '';
        $this->resetValidation('archive_reason');
    }

    /**
     * Take a document out of the expiry watch. It stays on the page, in the
     * history, with who archived it and why.
     */
    public function archiveDocument()
    {
        $this->authorizeAbility('vendors.archive_documents');

        $this->validate(['archive_reason' => 'required|string|max:255']);

        $document = $this->ownDocument((int) $this->archiving_document_id);

        abort_unless($document->isActive(), 422, __('Only an active document can be archived.'));

        $document->archive(Auth::user(), trim($this->archive_reason));

        $this->cancelArchive();

        session()->flash('message', __('Document archived.'));
    }

    public function reactivateDocument(int $documentId)
    {
        $this->authorizeAbility('vendors.archive_documents');

        $document = $this->ownDocument($documentId);

        abort_unless($document->isArchived(), 422, __('Only an archived document can be reactivated.'));

        $document->reactivate();

        session()->flash('message', __('Document reactivated.'));
    }

    public function toggleEmployeeForm()
    {
        $this->showEmployeeForm = !$this->showEmployeeForm;
        if (!$this->showEmployeeForm) {
            $this->resetEmployeeForm();
        }
    }

    public function resetEmployeeForm()
    {
        $this->employee_title = '';
        $this->employee_name = '';
        $this->employee_phone = '';
        $this->employee_email = '';
        $this->employee_notes = '';
        $this->resetValidation([
            'employee_title',
            'employee_name',
            'employee_phone',
            'employee_email',
            'employee_notes',
        ]);
    }

    public function saveEmployee()
    {
        $this->validate([
            'employee_name' => 'required|string|max:255',
            'employee_title' => 'nullable|string|max:255',
            'employee_phone' => 'nullable|string|max:50',
            'employee_email' => 'nullable|email|max:255',
            'employee_notes' => 'nullable|string|max:1000',
        ]);

        SubcontractorEmployee::create([
            'subcontractor_id' => $this->subcontractor->id,
            'title' => $this->employee_title ?: null,
            'name' => $this->employee_name,
            'phone' => $this->employee_phone ?: null,
            'email' => $this->employee_email ?: null,
            'notes' => $this->employee_notes ?: null,
        ]);

        $this->resetEmployeeForm();
        $this->showEmployeeForm = false;

        session()->flash('message', __('Employee added successfully!'));
    }

    public function deleteEmployee(int $employeeId)
    {
        $this->authorizeAbility('vendors.edit');

        $employee = SubcontractorEmployee::where('id', $employeeId)
            ->where('subcontractor_id', $this->subcontractor->id)
            ->firstOrFail();

        $employee->delete();

        session()->flash('message', __('Employee deleted successfully!'));
    }

    public function deleteDocument(int $documentId)
    {
        $this->authorizeAbility('vendors.delete');

        $this->ownDocument($documentId)->delete();

        session()->flash('message', __('Document deleted successfully!'));
    }

    /**
     * A document by id, but only one of this vendor's — the id came from the
     * browser and proves nothing on its own.
     */
    protected function ownDocument(int $documentId): SubcontractorDocument
    {
        return SubcontractorDocument::where('id', $documentId)
            ->where('subcontractor_id', $this->subcontractor->id)
            ->firstOrFail();
    }

    public function confirmDeleteSubcontractor()
    {
        $this->authorizeAbility('vendors.delete');

        if ($this->subcontractor->contracts()->exists() || $this->subcontractor->paymentBatches()->exists()) {
            return;
        }

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'delete-subcontractor-modal');
    }

    public function deleteSubcontractor()
    {
        $this->authorizeAbility('vendors.delete');

        // Re-check as a safety guard
        if ($this->subcontractor->contracts()->exists() || $this->subcontractor->paymentBatches()->exists()) {
            $this->cancelDeleteSubcontractor();
            return;
        }

        // A company that is also a supplier only loses its subcontractor
        // classification — the record survives on the Suppliers page, and its
        // documents and employees are kept (restored by re-flagging).
        if ($this->subcontractor->is_supplier) {
            $this->subcontractor->is_subcontractor = false;
            $this->subcontractor->save();

            session()->flash('message', __('Subcontractor classification removed. The company still exists as a supplier.'));

            return redirect()->route('subcontractors.index');
        }

        DB::transaction(function () {
            $this->subcontractor->delete();
        });

        session()->flash('message', __('Subcontractor deleted successfully!'));

        return redirect()->route('subcontractors.index');
    }

    public function cancelDeleteSubcontractor()
    {
        $this->showDeleteModal = false;
        $this->dispatch('close-modal', 'delete-subcontractor-modal');
    }

    public function getSelectedDocumentTypeProperty()
    {
        if (!$this->document_type_id) {
            return null;
        }
        return DocumentType::find($this->document_type_id);
    }

    public function render()
    {
        $documentTypes = DocumentType::active()->ordered()->get();
        $documents = $this->subcontractor->documents()
            ->with(['documentType', 'uploadedBy', 'archivedBy', 'supersededBy', 'supersedes'])
            ->orderBy('created_at', 'desc')
            ->get();

        // How many versions each document's chain holds, for the History
        // button — counted from the "replaced by" pointers, no queries.
        $predecessorOf = $documents->whereNotNull('superseded_by_id')->keyBy('superseded_by_id');
        $chainLength = function (SubcontractorDocument $document) use ($predecessorOf) {
            $length = 1;
            $current = $document;
            while (isset($predecessorOf[$current->id]) && $length < 100) {
                $current = $predecessorOf[$current->id];
                $length++;
            }

            return $length;
        };

        // One block per type that has anything filed: the documents in force
        // on top, the archived ones underneath in their own list. Superseded
        // documents are reached only through the History of the document that
        // replaced them — they are that document's past, not the type's.
        $documentGroups = $documents
            ->groupBy('document_type_id')
            ->map(fn ($group) => [
                'type' => $group->first()->documentType,
                'active' => $group->filter->isActive()->values(),
                'archived' => $group->filter->isArchived()->values(),
                'history' => $group->reject->isActive()->values(),
                'expiry' => SubcontractorDocument::worstExpiry($group->filter->isActive()->map->expiry_status),
            ])
            ->sortBy(fn ($group) => [$group['type']->sort_order, $group['type']->name])
            ->values();

        // Every type that asks for a date, and where this vendor stands on it —
        // "missing" is the state the table cannot show, because there is no row.
        $requiredTypes = $documentTypes
            ->where('requires_expiration', true)
            ->map(function (DocumentType $type) use ($documents) {
                $active = $documents->where('document_type_id', $type->id)->filter->isActive();

                return [
                    'type' => $type,
                    'status' => $active->isEmpty() ? 'missing' : SubcontractorDocument::worstExpiry($active->map->expiry_status),
                    // The one the Renew shortcut targets: the soonest dated
                    // document; an undated one only when there is nothing else.
                    'document' => $active->sortBy(fn ($d) => $d->expiration_date?->getTimestamp() ?? PHP_INT_MAX)->first(),
                ];
            })
            ->values();

        $documentCounts = [
            'active' => $documents->filter->isActive()->count(),
            'expiring' => $documents->filter(fn ($d) => $d->expiry_status === SubcontractorDocument::EXPIRY_EXPIRING_SOON)->count(),
            'expired' => $documents->filter(fn ($d) => $d->expiry_status === SubcontractorDocument::EXPIRY_EXPIRED)->count(),
            'undated' => $documents->filter(fn ($d) => $d->expiry_status === SubcontractorDocument::EXPIRY_UNDATED)->count(),
            'history' => $documents->reject->isActive()->count(),
            'tracked' => $documents->filter(fn ($d) => $d->isActive() && $d->documentType->requires_expiration && $d->documentType->is_active && $d->expiration_date)->count(),
        ];

        $documentHealth = match (true) {
            $documentCounts['expired'] > 0 => SubcontractorDocument::EXPIRY_EXPIRED,
            $documentCounts['expiring'] > 0 => SubcontractorDocument::EXPIRY_EXPIRING_SOON,
            $documentCounts['tracked'] > 0 => SubcontractorDocument::EXPIRY_VALID,
            default => 'none',
        };

        $employees = $this->subcontractor->employees()
            ->orderBy('name')
            ->get();

        $linkedContracts = $this->subcontractor->contracts()->count();
        $linkedPaymentBatches = $this->subcontractor->paymentBatches()->count();

        $historyDocument = $this->history_document_id
            ? $documents->firstWhere('id', $this->history_document_id)
            : null;

        $historyEntries = $historyDocument ? $this->chainOf($historyDocument, $documents) : collect();

        return view('livewire.subcontractor.subcontractor-show', [
            'documentTypes' => $documentTypes,
            'documents' => $documents,
            'documentGroups' => $documentGroups,
            'historyDocument' => $historyDocument,
            'historyEntries' => $historyEntries,
            'chainLength' => $chainLength,
            'requiredTypes' => $requiredTypes,
            'documentCounts' => $documentCounts,
            'documentHealth' => $documentHealth,
            'employees' => $employees,
            'linkedContracts' => $linkedContracts,
            'linkedPaymentBatches' => $linkedPaymentBatches,
        ])->layout('components.layouts.app');
    }
}
