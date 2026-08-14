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
            'is_dual' => (bool) $subcontractor->is_supplier,
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
            // A company that is also a supplier only loses its subcontractor
            // classification — the record survives on the Suppliers page, and
            // its documents and employees are kept (restored by re-flagging).
            // Full deletes clean up document files via the model hook.
            if ($subcontractor->is_supplier) {
                $subcontractor->is_subcontractor = false;
                $subcontractor->save();
            } else {
                $subcontractor->delete();
            }
        });

        $this->showDeleteModal = false;
        $this->deletingSubcontractorId = null;
        $this->deleteSubcontractorData = [];
        $this->resetPage();

        session()->flash('message', $subcontractor->is_supplier
            ? __('Subcontractor classification removed. The company still exists as a supplier.')
            : __('Subcontractor deleted successfully!'));
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
                    $q->where('name', 'like', '%' . $this->search . '%')
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
