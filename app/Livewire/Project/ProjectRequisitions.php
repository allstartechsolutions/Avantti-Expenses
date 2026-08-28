<?php

namespace App\Livewire\Project;

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
 * Purchase requisitions (solicitações de compra) raised on this project,
 * including the ones raised on its job sites.
 */
class ProjectRequisitions extends Component
{
    use AuthorizesAbility, ManagesRequisitions, WithFileUploads, WithPagination;

    public Project $project;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $priorityFilter = '';
    public $locationFilter = '';
    public $assignmentFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'priorityFilter' => ['except' => ''],
        'locationFilter' => ['except' => ''],
        'assignmentFilter' => ['except' => ''],
    ];

    public function mount(Project $project)
    {
        $this->authorizeAbility('requisitions.view', $project);

        $this->project = $project;
    }

    protected function contextProject(): Project
    {
        return $this->project;
    }

    /** The project page covers every location, so none is fixed. */
    protected function contextJobSite(): ?JobSite
    {
        return null;
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

    public function updatedLocationFilter()
    {
        $this->resetPage();
    }

    public function updatedAssignmentFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->typeFilter = '';
        $this->priorityFilter = '';
        $this->locationFilter = '';
        $this->assignmentFilter = '';
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || $this->typeFilter !== ''
            || $this->priorityFilter !== ''
            || $this->locationFilter !== ''
            || $this->assignmentFilter !== '';
    }

    public function render()
    {
        $query = PurchaseRequisition::where('project_id', $this->project->id)
            ->with(['jobSite', 'requestedBy', 'createdBy', 'budgetItem', 'quotations', 'assignedBuyer'])
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

        if ($this->locationFilter === 'project') {
            $query->whereNull('job_site_id');
        } elseif ($this->locationFilter) {
            $query->where('job_site_id', $this->locationFilter);
        }

        // "Mine" is the buyer's own queue; "unassigned" is the bucket that
        // stops a null default becoming a silent hole.
        if ($this->assignmentFilter === 'mine') {
            $query->where('assigned_buyer_id', auth()->id());
        } elseif ($this->assignmentFilter === 'unassigned') {
            $query->whereNull('assigned_buyer_id');
        }

        $requisitions = $query
            // Urgent first, then normal, then low. Written as a CASE rather
            // than FIELD(), which is MySQL-only and made these two screens
            // impossible to cover with a test.
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $base = fn () => PurchaseRequisition::where('project_id', $this->project->id);

        $stats = [
            'total' => $base()->count(),
            'pending' => $base()->where('status', 'pending')->count(),
            'approved' => $base()->where('status', 'approved')->count(),
            'urgent_open' => $base()->open()->where('priority', 'urgent')->count(),
            'unassigned' => $base()->whereIn('status', ['approved', 'quoted'])->whereNull('assigned_buyer_id')->count(),
            'mine' => $base()->where('assigned_buyer_id', auth()->id())->open()->count(),
            'overdue' => $base()->open()->whereNotNull('needed_by')->whereDate('needed_by', '<', now())->count(),
        ];

        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        return view('livewire.project.project-requisitions', [
            'requisitions' => $requisitions,
            'stats' => $stats,
            'jobSites' => $jobSites,
            'viewingRequisition' => $this->viewingRequisition(),
            'catalogSuggestions' => $this->catalogSuggestions(),
            'budgetItemSuggestions' => $this->budgetItemSuggestions(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ])->layout('components.layouts.app');
    }
}
