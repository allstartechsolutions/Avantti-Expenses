<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\ManagesQuotations;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Quotation;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Quotation rounds (cotações) raised on this project, including the ones
 * raised on its job sites.
 */
class ProjectQuotations extends Component
{
    use AuthorizesAdmin, ManagesQuotations, WithFileUploads, WithPagination;

    public Project $project;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $locationFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'locationFilter' => ['except' => ''],
    ];

    public function mount(Project $project)
    {
        $this->project = $project;
        $this->openRequisitionFromQuery();
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

    public function updatedLocationFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->typeFilter = '';
        $this->locationFilter = '';
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || $this->typeFilter !== ''
            || $this->locationFilter !== '';
    }

    public function render()
    {
        $query = Quotation::where('project_id', $this->project->id)
            ->with(['jobSite', 'createdBy', 'requisition', 'quotationVendors.items'])
            ->withCount('items');

        if ($this->search) {
            $search = ltrim(trim($this->search), '#');
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('items', function ($sq) use ($search) {
                        $sq->where('item_name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('quotationVendors.vendor', function ($sq) use ($search) {
                        $sq->where('name', 'like', '%'.$search.'%');
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

        if ($this->locationFilter === 'project') {
            $query->whereNull('job_site_id');
        } elseif ($this->locationFilter) {
            $query->where('job_site_id', $this->locationFilter);
        }

        $quotations = $query->orderBy('created_at', 'desc')->paginate(15);

        $base = fn () => Quotation::where('project_id', $this->project->id);

        $stats = [
            'total' => $base()->count(),
            'awaiting' => $base()->whereIn('status', ['sent', 'comparing', 'negotiating'])->count(),
            'awarded' => $base()->whereIn('status', ['awarded', 'converted'])->count(),
            'overdue' => $base()
                ->whereIn('status', ['sent', 'comparing', 'negotiating'])
                ->whereNotNull('responses_due_at')
                ->whereDate('responses_due_at', '<', now())
                ->count(),
        ];

        return view('livewire.project.project-quotations', [
            'quotations' => $quotations,
            'stats' => $stats,
            'jobSites' => $this->project->jobSites()->orderBy('job_site_name')->get(),
            'viewingQuotation' => $this->viewingQuotation(),
            'catalogSuggestions' => $this->catalogSuggestions(),
            'budgetItemSuggestions' => $this->budgetItemSuggestions(),
            'vendorSuggestions' => $this->vendorSuggestions(),
            'quotableRequisitions' => $this->quotableRequisitions(),
            'rfqEmails' => $this->rfqEmails(),
            'pricingVendorRow' => $this->pricingVendorRow(),
            'comparison' => $this->comparison(),
            'awardingQuotation' => $this->awardingQuotation(),
        ])->layout('components.layouts.app');
    }
}
