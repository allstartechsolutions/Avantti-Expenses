<?php

namespace App\Livewire\Project;

use App\Models\Project;
use App\Models\PurchaseOrder;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectPurchaseOrders extends Component
{
    use WithPagination;

    public Project $project;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $locationFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'locationFilter' => ['except' => ''],
    ];

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedLocationFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->locationFilter = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = PurchaseOrder::where('project_id', $this->project->id)
            ->with(['supplier', 'jobSite', 'createdBy']);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('po_number', 'like', '%' . $this->search . '%')
                    ->orWhere('notes', 'like', '%' . $this->search . '%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Apply location filter
        if ($this->locationFilter === 'project') {
            $query->whereNull('job_site_id');
        } elseif ($this->locationFilter) {
            $query->where('job_site_id', $this->locationFilter);
        }

        $purchaseOrders = $query->orderBy('created_at', 'desc')->paginate(15);

        // Summary statistics
        $stats = [
            'total' => PurchaseOrder::where('project_id', $this->project->id)->count(),
            'pending' => PurchaseOrder::where('project_id', $this->project->id)->where('status', 'pending')->count(),
            'total_amount' => PurchaseOrder::where('project_id', $this->project->id)->sum('total_amount') / 100,
            'approved_amount' => PurchaseOrder::where('project_id', $this->project->id)
                ->where('status', 'approved')
                ->sum('total_amount') / 100,
        ];

        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        return view('livewire.project.project-purchase-orders', [
            'purchaseOrders' => $purchaseOrders,
            'stats' => $stats,
            'jobSites' => $jobSites,
        ])->layout('components.layouts.app');
    }
}
