<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesRequisitions;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\User;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Requisitions raised on this job site. Same page as the project level,
 * with the location fixed to this job site.
 */
class JobSiteRequisitions extends Component
{
    use AuthorizesAbility, ManagesRequisitions, WithFileUploads, WithPagination;

    public JobSite $jobSite;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $priorityFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'priorityFilter' => ['except' => ''],
    ];

    public function mount(JobSite $jobSite)
    {
        $this->authorizeAbility('requisitions.view', $jobSite);

        $this->jobSite = $jobSite->load('project');
    }

    protected function contextProject(): Project
    {
        return $this->jobSite->project;
    }

    protected function contextJobSite(): ?JobSite
    {
        return $this->jobSite;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedPriorityFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->typeFilter = '';
        $this->priorityFilter = '';
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || $this->typeFilter !== ''
            || $this->priorityFilter !== '';
    }

    public function render()
    {
        $query = $this->scopedQuery()
            ->with(['jobSite', 'requestedBy', 'createdBy', 'budgetItem', 'quotations'])
            ->withCount('items');

        if ($this->search) {
            $search = ltrim(trim($this->search), '#');
            $query->where(function ($q) use ($search) {
                $q->where('requisition_number', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhere('justification', 'like', '%'.$search.'%')
                    ->orWhere('requested_by_name', 'like', '%'.$search.'%')
                    ->orWhereHas('requestedBy', function ($sq) use ($search) {
                        $sq->where('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('items', function ($sq) use ($search) {
                        $sq->where('item_name', 'like', '%'.$search.'%');
                    });

                if (is_numeric($search)) {
                    $q->orWhere('id', $search);
                }
            });
        }

        if ($this->statusFilter === 'open') {
            $query->open();
        } elseif ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }

        $requisitions = $query
            // Urgent first, then normal, then low. Written as a CASE rather
            // than FIELD(), which is MySQL-only and made these two screens
            // impossible to cover with a test.
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $stats = [
            'total' => $this->scopedQuery()->count(),
            'pending' => $this->scopedQuery()->where('status', 'pending')->count(),
            'approved' => $this->scopedQuery()->where('status', 'approved')->count(),
            'urgent_open' => $this->scopedQuery()->open()->where('priority', 'urgent')->count(),
            'overdue' => $this->scopedQuery()->open()->whereNotNull('needed_by')->whereDate('needed_by', '<', now())->count(),
        ];

        return view('livewire.job-site.job-site-requisitions', [
            'requisitions' => $requisitions,
            'stats' => $stats,
            'viewingRequisition' => $this->viewingRequisition(),
            'catalogSuggestions' => $this->catalogSuggestions(),
            'budgetItemSuggestions' => $this->budgetItemSuggestions(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ])->layout('components.layouts.app');
    }
}
