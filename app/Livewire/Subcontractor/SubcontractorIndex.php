<?php

namespace App\Livewire\Subcontractor;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Subcontractor;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class SubcontractorIndex extends Component
{
    use AuthorizesAdmin, WithPagination;

    public $search = '';
    public $perPage = 10;

    // Delete modal
    public $showDeleteModal = false;
    public $deletingSubcontractorId = null;
    public $deleteSubcontractorData = [];

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function confirmDeleteSubcontractor($subcontractorId)
    {
        $this->authorizeAdmin();

        $subcontractor = Subcontractor::findOrFail($subcontractorId);

        if ($subcontractor->contracts()->exists() || $subcontractor->paymentBatches()->exists()) {
            return;
        }

        $this->deletingSubcontractorId = $subcontractorId;
        $this->deleteSubcontractorData = [
            'name' => $subcontractor->company_name,
            'documents' => $subcontractor->documents()->count(),
            'employees' => $subcontractor->employees()->count(),
        ];

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'delete-subcontractor-modal');
    }

    public function deleteSubcontractor()
    {
        $this->authorizeAdmin();

        $subcontractor = Subcontractor::findOrFail($this->deletingSubcontractorId);

        // Re-check as a safety guard
        if ($subcontractor->contracts()->exists() || $subcontractor->paymentBatches()->exists()) {
            $this->cancelDeleteSubcontractor();
            return;
        }

        DB::transaction(function () use ($subcontractor) {
            // Delete documents via Eloquent so the file cleanup hook fires
            $subcontractor->documents->each->delete();
            $subcontractor->delete();
        });

        $this->showDeleteModal = false;
        $this->deletingSubcontractorId = null;
        $this->deleteSubcontractorData = [];

        session()->flash('message', 'Subcontractor deleted successfully!');
    }

    public function cancelDeleteSubcontractor()
    {
        $this->showDeleteModal = false;
        $this->deletingSubcontractorId = null;
        $this->deleteSubcontractorData = [];
        $this->dispatch('close-modal', 'delete-subcontractor-modal');
    }

    public function render()
    {
        $subcontractors = Subcontractor::query()
            ->with('createdBy')
            ->withCount(['contracts', 'paymentBatches'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('company_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.subcontractor.subcontractor-index', [
            'subcontractors' => $subcontractors,
        ])->layout('components.layouts.app');
    }
}
