<?php

namespace App\Livewire\Report;

use App\Models\Client;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\ExpenseReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class ExpenseReport extends Component
{
    public string $fromDate = '';
    public string $toDate = '';
    public string $clientFilter = '';
    public string $projectFilter = '';
    public string $jobSiteFilter = '';
    public string $vendorFilter = '';
    public string $categoryFilter = '';
    public string $statusFilter = 'all';

    public string $view = 'project'; // project | vendor | costcode | detail

    protected $queryString = [
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
        'clientFilter' => ['except' => ''],
        'projectFilter' => ['except' => ''],
        'jobSiteFilter' => ['except' => ''],
        'vendorFilter' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'view' => ['except' => 'project'],
    ];

    public function mount(): void
    {
        if ($this->fromDate === '') {
            $this->fromDate = Carbon::now()->startOfYear()->toDateString();
        }
        if ($this->toDate === '') {
            $this->toDate = Carbon::now()->endOfYear()->toDateString();
        }
    }

    /**
     * Job site list depends on the selected project, so clear a stale selection
     * when the project changes.
     */
    public function updatedProjectFilter(): void
    {
        $this->jobSiteFilter = '';
    }

    public function setCurrentMonth(): void
    {
        $this->fromDate = Carbon::now()->startOfMonth()->toDateString();
        $this->toDate = Carbon::now()->endOfMonth()->toDateString();
    }

    public function setCurrentQuarter(): void
    {
        $this->fromDate = Carbon::now()->firstOfQuarter()->toDateString();
        $this->toDate = Carbon::now()->lastOfQuarter()->toDateString();
    }

    public function setYearToDate(): void
    {
        $this->fromDate = Carbon::now()->startOfYear()->toDateString();
        $this->toDate = Carbon::now()->toDateString();
    }

    public function setLastYear(): void
    {
        $this->fromDate = Carbon::now()->subYear()->startOfYear()->toDateString();
        $this->toDate = Carbon::now()->subYear()->endOfYear()->toDateString();
    }

    protected function service(): ExpenseReportService
    {
        return new ExpenseReportService(
            $this->fromDate,
            $this->toDate,
            $this->projectFilter,
            $this->jobSiteFilter,
            $this->vendorFilter,
            $this->categoryFilter,
            $this->clientFilter,
            $this->statusFilter,
        );
    }

    public function getProjectsProperty(): Collection
    {
        return Project::orderBy('project_name')->get(['id', 'project_name']);
    }

    public function getClientsProperty(): Collection
    {
        return Client::whereHas('projects')->orderBy('company_name')->get(['id', 'company_name']);
    }

    public function getJobSitesProperty(): Collection
    {
        return JobSite::query()
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->orderBy('job_site_name')
            ->get(['id', 'job_site_name']);
    }

    public function getVendorsProperty(): Collection
    {
        return Supplier::orderBy('name')->get(['id', 'name']);
    }

    public function render()
    {
        $service = $this->service();

        return view('livewire.report.expense-report', [
            'kpis' => $service->kpis(),
            'byProject' => $service->byProject(),
            'byVendor' => $service->byVendor(),
            'byCostCode' => $service->byCostCode(),
            'detail' => $service->detail(),
            'projects' => $this->projects,
            'clients' => $this->clients,
            'jobSites' => $this->jobSites,
            'vendors' => $this->vendors,
        ])->layout('components.layouts.app');
    }
}
