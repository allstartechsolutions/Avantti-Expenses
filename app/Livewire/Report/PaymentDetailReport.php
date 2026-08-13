<?php

namespace App\Livewire\Report;

use App\Models\Client;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\Supplier;
use App\Services\PaymentDetailReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentDetailReport extends Component
{
    public string $fromDate = '';
    public string $toDate = '';
    public string $clientFilter = '';
    public string $projectFilter = '';
    public string $jobSiteFilter = '';
    public string $vendorFilter = '';
    public string $subcontractorFilter = '';

    /** Multi-select: any of paid|pending|overdue. Empty = all statuses. */
    public $statusFilter = [];

    public string $typeFilter = 'all';

    public string $view = 'detail'; // detail | project | vendor

    protected $queryString = [
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
        'clientFilter' => ['except' => ''],
        'projectFilter' => ['except' => ''],
        'jobSiteFilter' => ['except' => ''],
        'vendorFilter' => ['except' => ''],
        'subcontractorFilter' => ['except' => ''],
        'statusFilter' => ['except' => []],
        'typeFilter' => ['except' => 'all'],
        'view' => ['except' => 'detail'],
    ];

    public function mount(): void
    {
        if ($this->fromDate === '') {
            $this->fromDate = Carbon::now()->startOfMonth()->toDateString();
        }
        if ($this->toDate === '') {
            $this->toDate = Carbon::now()->endOfMonth()->toDateString();
        }

        // Tolerate old bookmarked links where statusFilter was a single
        // string ('all', 'paid', ...) — anything invalid just drops out.
        $this->statusFilter = array_values(array_intersect(
            (array) $this->statusFilter,
            ['paid', 'pending', 'overdue']
        ));
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

    public function setNextMonth(): void
    {
        $this->fromDate = Carbon::now()->addMonth()->startOfMonth()->toDateString();
        $this->toDate = Carbon::now()->addMonth()->endOfMonth()->toDateString();
    }

    public function setNextThreeMonths(): void
    {
        $this->fromDate = Carbon::now()->startOfMonth()->toDateString();
        $this->toDate = Carbon::now()->addMonths(2)->endOfMonth()->toDateString();
    }

    public function setThisYear(): void
    {
        $this->fromDate = Carbon::now()->startOfYear()->toDateString();
        $this->toDate = Carbon::now()->endOfYear()->toDateString();
    }

    protected function service(): PaymentDetailReportService
    {
        return new PaymentDetailReportService(
            $this->fromDate,
            $this->toDate,
            $this->projectFilter,
            $this->jobSiteFilter,
            $this->vendorFilter,
            $this->subcontractorFilter,
            $this->clientFilter,
            $this->statusFilter,
            in_array($this->typeFilter, ['all', 'expenses', 'contracts'], true) ? $this->typeFilter : 'all',
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

    public function getSubcontractorsProperty(): Collection
    {
        return Subcontractor::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Export the currently active tab (respecting all filters) as CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $service = $this->service();

        [$headers, $rows, $totals] = match ($this->view) {
            'project' => $this->projectCsv($service),
            'vendor' => $this->vendorCsv($service),
            default => $this->detailCsv($service),
        };

        $filename = 'payment-details-' . $this->view . '-' . $this->fromDate . '-to-' . $this->toDate . '.csv';

        return new StreamedResponse(function () use ($headers, $rows, $totals) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            if ($totals !== null) {
                fputcsv($out, []);
                fputcsv($out, $totals);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function detailCsv(PaymentDetailReportService $service): array
    {
        $headers = ['Date', 'Type', 'Vendor', 'Item', 'Project', 'Job Site', 'Installment', 'Status', 'Paid Date', 'Paid By', 'Amount'];
        $rows = $service->rows()->map(fn ($r) => [
            $r['date']?->format('Y-m-d') ?? '',
            $r['type'] === 'contract' ? 'Contract' : 'Expense',
            $r['vendor'] ?? '',
            $r['item'] ?? '',
            $r['project'] ?? '',
            $r['job_site'] ?? '',
            $r['installment_label'] ?? '',
            ucfirst($r['status']),
            $r['paid_date']?->format('Y-m-d') ?? '',
            $r['paid_by'] ?? '',
            $this->money($r['amount']),
        ])->all();

        $k = $service->kpis();
        $totals = ['Total', '', '', '', '', '', '', '', '', '', $this->money($k['total'])];

        return [$headers, $rows, $totals];
    }

    protected function projectCsv(PaymentDetailReportService $service): array
    {
        $headers = ['Project', 'Job Site', 'Payments', 'Total', 'Paid', 'Pending', 'Overdue'];
        $rows = [];

        foreach ($service->byProject() as $proj) {
            $rows[] = [
                $proj['project'],
                '',
                $proj['count'],
                $this->money($proj['total']),
                $this->money($proj['paid']),
                $this->money($proj['pending']),
                $this->money($proj['overdue']),
            ];
            foreach ($proj['jobsites'] as $js) {
                $rows[] = [
                    $proj['project'],
                    $js['job_site'] ?? 'Project-level',
                    $js['count'],
                    $this->money($js['total']),
                    $this->money($js['paid']),
                    $this->money($js['pending']),
                    $this->money($js['overdue']),
                ];
            }
        }

        $k = $service->kpis();
        $totals = ['Total', '', $k['count'], $this->money($k['total']), $this->money($k['paid']), $this->money($k['pending']), $this->money($k['overdue'])];

        return [$headers, $rows, $totals];
    }

    protected function vendorCsv(PaymentDetailReportService $service): array
    {
        $headers = ['Vendor', 'Type', 'Payments', 'Total', 'Paid', 'Pending', 'Overdue'];
        $rows = $service->byVendor()->map(fn ($v) => [
            $v['vendor'] ?? 'No vendor',
            $v['type'] === 'contract' ? 'Contract' : 'Expense',
            $v['count'],
            $this->money($v['total']),
            $this->money($v['paid']),
            $this->money($v['pending']),
            $this->money($v['overdue']),
        ])->all();

        $k = $service->kpis();
        $totals = ['Total', '', $k['count'], $this->money($k['total']), $this->money($k['paid']), $this->money($k['pending']), $this->money($k['overdue'])];

        return [$headers, $rows, $totals];
    }

    public function render()
    {
        $service = $this->service();

        return view('livewire.report.payment-detail-report', [
            'kpis' => $service->kpis(),
            'rows' => $service->rows(),
            'byProject' => $service->byProject(),
            'byVendor' => $service->byVendor(),
            'projects' => $this->projects,
            'clients' => $this->clients,
            'jobSites' => $this->jobSites,
            'vendors' => $this->vendors,
            'subcontractors' => $this->subcontractors,
        ])->layout('components.layouts.app');
    }
}
