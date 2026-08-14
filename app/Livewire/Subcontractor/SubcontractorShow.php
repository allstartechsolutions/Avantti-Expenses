<?php

namespace App\Livewire\Subcontractor;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\DocumentType;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use App\Models\SubcontractorEmployee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubcontractorShow extends Component
{
    use AuthorizesAdmin, WithFileUploads;

    public Subcontractor $subcontractor;
    public string $activeTab = 'overview';

    // Document upload form
    public $document_type_id = '';
    public $document_file;
    public $expiration_date = '';
    public $document_notes = '';
    public bool $showUploadForm = false;

    // Employee form
    public $employee_title = '';
    public $employee_name = '';
    public $employee_phone = '';
    public $employee_email = '';
    public $employee_notes = '';
    public bool $showEmployeeForm = false;

    // Delete modal
    public $showDeleteModal = false;

    protected function rules()
    {
        $rules = [
            'document_type_id' => 'required|exists:document_types,id',
            'document_file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
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

    protected $validationAttributes = [
        'document_type_id' => 'document type',
        'document_file' => 'document file',
        'expiration_date' => 'expiration date',
        'document_notes' => 'notes',
        'employee_name' => 'name',
        'employee_title' => 'title',
        'employee_phone' => 'phone',
        'employee_email' => 'email',
        'employee_notes' => 'notes',
    ];

    public function mount(Subcontractor $subcontractor)
    {
        $this->subcontractor = $subcontractor->load('createdBy');
    }

    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function toggleUploadForm()
    {
        $this->showUploadForm = !$this->showUploadForm;
        if (!$this->showUploadForm) {
            $this->resetUploadForm();
        }
    }

    public function resetUploadForm()
    {
        $this->document_type_id = '';
        $this->document_file = null;
        $this->expiration_date = '';
        $this->document_notes = '';
        $this->resetValidation([
            'document_type_id',
            'document_file',
            'expiration_date',
            'document_notes',
        ]);
    }

    public function updatedDocumentTypeId($value)
    {
        // Reset expiration date when document type changes
        $this->expiration_date = '';
    }

    public function uploadDocument()
    {
        $this->validate();

        $file = $this->document_file;
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('subcontractor-documents/' . $this->subcontractor->id, 'local');

        SubcontractorDocument::create([
            'subcontractor_id' => $this->subcontractor->id,
            'document_type_id' => $this->document_type_id,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $file->getSize(),
            'expiration_date' => $this->expiration_date ?: null,
            'notes' => $this->document_notes ?: null,
            'uploaded_by' => Auth::id(),
        ]);

        $this->resetUploadForm();
        $this->showUploadForm = false;

        session()->flash('message', __('Document uploaded successfully!'));
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
        $this->authorizeAdmin();

        $employee = SubcontractorEmployee::where('id', $employeeId)
            ->where('subcontractor_id', $this->subcontractor->id)
            ->firstOrFail();

        $employee->delete();

        session()->flash('message', __('Employee deleted successfully!'));
    }

    public function deleteDocument(int $documentId)
    {
        $document = SubcontractorDocument::where('id', $documentId)
            ->where('subcontractor_id', $this->subcontractor->id)
            ->firstOrFail();

        $document->delete();

        session()->flash('message', __('Document deleted successfully!'));
    }

    public function confirmDeleteSubcontractor()
    {
        $this->authorizeAdmin();

        if ($this->subcontractor->contracts()->exists() || $this->subcontractor->paymentBatches()->exists()) {
            return;
        }

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'delete-subcontractor-modal');
    }

    public function deleteSubcontractor()
    {
        $this->authorizeAdmin();

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
        $documentTypes = DocumentType::ordered()->get();
        $documents = $this->subcontractor->documents()
            ->with(['documentType', 'uploadedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $employees = $this->subcontractor->employees()
            ->orderBy('name')
            ->get();

        $linkedContracts = $this->subcontractor->contracts()->count();
        $linkedPaymentBatches = $this->subcontractor->paymentBatches()->count();

        return view('livewire.subcontractor.subcontractor-show', [
            'documentTypes' => $documentTypes,
            'documents' => $documents,
            'employees' => $employees,
            'linkedContracts' => $linkedContracts,
            'linkedPaymentBatches' => $linkedPaymentBatches,
        ])->layout('components.layouts.app');
    }
}
