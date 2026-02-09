<?php

namespace App\Livewire\Subcontractor;

use App\Models\DocumentType;
use App\Models\Subcontractor;
use App\Models\SubcontractorDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubcontractorShow extends Component
{
    use WithFileUploads;

    public Subcontractor $subcontractor;
    public string $activeTab = 'overview';

    // Document upload form
    public $document_type_id = '';
    public $document_file;
    public $expiration_date = '';
    public $document_notes = '';
    public bool $showUploadForm = false;

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
        $this->resetValidation();
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

        session()->flash('message', 'Document uploaded successfully!');
    }

    public function deleteDocument(int $documentId)
    {
        $document = SubcontractorDocument::where('id', $documentId)
            ->where('subcontractor_id', $this->subcontractor->id)
            ->firstOrFail();

        $document->delete();

        session()->flash('message', 'Document deleted successfully!');
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

        return view('livewire.subcontractor.subcontractor-show', [
            'documentTypes' => $documentTypes,
            'documents' => $documents,
        ])->layout('components.layouts.app');
    }
}
