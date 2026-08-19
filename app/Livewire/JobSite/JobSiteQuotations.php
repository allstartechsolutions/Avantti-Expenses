<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\ManagesQuotations;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Quotation rounds raised on this job site. Same page as the project level,
 * with the location fixed to this job site.
 */
class JobSiteQuotations extends Component
{
    use AuthorizesAdmin, ManagesQuotations, WithFileUploads, WithPagination;

    public JobSite $jobSite;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
    ];

    public function mount(JobSite $jobSite)
    {
        $this->jobSite = $jobSite->load('project');
        $this->openRequisitionFromQuery();
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

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->typeFilter = '';
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->statusFilter !== '' || $this->typeFilter !== '';
    }

    public function render()
    {
        $query = $this->scopedQuery()
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

        $quotations = $query->orderBy('created_at', 'desc')->paginate(15);

        $stats = [
            'total' => $this->scopedQuery()->count(),
            'awaiting' => $this->scopedQuery()->whereIn('status', ['sent', 'comparing', 'negotiating'])->count(),
            'awarded' => $this->scopedQuery()->whereIn('status', ['awarded', 'converted'])->count(),
            'overdue' => $this->scopedQuery()
                ->whereIn('status', ['sent', 'comparing', 'negotiating'])
                ->whereNotNull('responses_due_at')
                ->whereDate('responses_due_at', '<', now())
                ->count(),
        ];

        return view('livewire.job-site.job-site-quotations', [
            'quotations' => $quotations,
            'stats' => $stats,
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
