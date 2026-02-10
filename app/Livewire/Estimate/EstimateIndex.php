<?php

namespace App\Livewire\Estimate;

use App\Models\Estimate;
use Livewire\Component;
use Livewire\WithPagination;

class EstimateIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function deleteEstimate(int $estimateId)
    {
        $estimate = Estimate::findOrFail($estimateId);

        if (!$estimate->canBeEdited()) {
            session()->flash('error', 'Only draft or sent estimates can be deleted.');
            return;
        }

        $estimate->items()->delete();
        $estimate->delete();

        session()->flash('message', 'Estimate deleted successfully!');
    }

    public function render()
    {
        $estimates = Estimate::query()
            ->with(['client', 'project'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('estimate_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('client', function ($q) {
                          $q->where('company_name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $statusCounts = [
            'all' => Estimate::count(),
            'draft' => Estimate::where('status', 'draft')->count(),
            'sent' => Estimate::where('status', 'sent')->count(),
            'accepted' => Estimate::where('status', 'accepted')->count(),
            'declined' => Estimate::where('status', 'declined')->count(),
        ];

        return view('livewire.estimate.estimate-index', [
            'estimates' => $estimates,
            'statusCounts' => $statusCounts,
        ])->layout('components.layouts.app');
    }
}
