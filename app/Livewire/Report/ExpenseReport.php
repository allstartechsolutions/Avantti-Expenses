<?php

namespace App\Livewire\Report;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\ExpenseReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReport extends Component
{
    use AuthorizesAbility;

    public string $fromDate = '';
    public string $toDate = '';
    public string $clientFilter = '';
    public string $projectFilter = '';
    public string $jobSiteFilter = '';
    public string $vendorFilter = '';
    public string $categoryFilter = '';
    public string $statusFilter = 'all';
    public string $dateBasis = 'expense'; // expense | due

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
        'dateBasis' => ['except' => 'expense'],
        'view' => ['except' => 'project'],
    ];

    public function mount(): void
    {
        $this->authorizeAbility('reports.expenses');

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

    /**
     * dateBasis is query-string bound, so guard against arbitrary values.
     */
    public function updatedDateBasis(): void
    {
        if (! in_array($this->dateBasis, ['expense', 'due'], true)) {
            $this->dateBasis = 'expense';
        }
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
            in_array($this->dateBasis, ['expense', 'due'], true) ? $this->dateBasis : 'expense',
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

    /**
     * Export the currently active tab (respecting all filters) as CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $service = $this->service();

        [$headers, $rows, $totals] = match ($this->view) {
            'vendor' => $this->vendorCsv($service),
            'costcode' => $this->costCodeCsv($service),
            'detail' => $this->detailCsv($service),
            default => $this->projectCsv($service),
        };

        $basis = $this->dateBasis === 'due' ? 'due-date' : 'expense-date';
        $filename = 'expense-report-' . $this->view . '-' . $basis . '-' . $this->fromDate . '-to-' . $this->toDate . '.csv';

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

    protected function projectCsv(ExpenseReportService $service): array
    {
        $headers = ['Project', 'Job Site', 'Expenses', 'Total', 'Paid', 'Outstanding', 'Overdue'];
        $rows = [];

        foreach ($service->byProject() as $proj) {
            $rows[] = [
                $proj['project'] ?? '',
                '',
                $proj['count'],
                $this->money($proj['total']),
                $this->money($proj['paid']),
                $this->money($proj['outstanding']),
                $this->money($proj['overdue']),
            ];
            foreach ($proj['jobsites'] as $js) {
                $rows[] = [
                    $proj['project'] ?? '',
                    $js['job_site'] ?? 'Project-level',
                    $js['count'],
                    $this->money($js['total']),
                    $this->money($js['paid']),
                    $this->money($js['outstanding']),
                    $this->money($js['overdue']),
                ];
            }
        }

        $k = $service->kpis();
        $totals = ['Total', '', $k['count'], $this->money($k['total']), $this->money($k['paid']), $this->money($k['outstanding']), $this->money($k['overdue'])];

        return [$headers, $rows, $totals];
    }

    protected function vendorCsv(ExpenseReportService $service): array
    {
        $headers = ['Vendor', 'Expenses', 'Total', 'Paid', 'Outstanding', 'Overdue'];
        $rows = $service->byVendor()->map(fn ($v) => [
            $v['vendor'] ?? 'No vendor',
            $v['count'],
            $this->money($v['total']),
            $this->money($v['paid']),
            $this->money($v['outstanding']),
            $this->money($v['overdue']),
        ])->all();

        $k = $service->kpis();
        $totals = ['Total', $k['count'], $this->money($k['total']), $this->money($k['paid']), $this->money($k['outstanding']), $this->money($k['overdue'])];

        return [$headers, $rows, $totals];
    }

    protected function costCodeCsv(ExpenseReportService $service): array
    {
        $headers = ['Cost Code', 'Line Items', 'Expenses', 'Contracted', 'Contract Paid', 'Total Committed'];
        $byCostCode = $service->byCostCode();
        $rows = $byCostCode->map(fn ($cc) => [
            $cc['code'],
            $cc['count'],
            $this->money($cc['expenses']),
            $this->money($cc['contracted']),
            $this->money($cc['contract_paid']),
            $this->money($cc['total']),
        ])->all();

        $totals = [
            'Total',
            $byCostCode->sum('count'),
            $this->money($byCostCode->sum('expenses')),
            $this->money($byCostCode->sum('contracted')),
            $this->money($byCostCode->sum('contract_paid')),
            $this->money($byCostCode->sum('total')),
        ];

        return [$headers, $rows, $totals];
    }

    protected function detailCsv(ExpenseReportService $service): array
    {
        $dueBasis = $this->dateBasis === 'due';
        $headers = [$dueBasis ? 'Due Date' : 'Date', 'Item', 'Vendor', 'Project', 'Job Site', 'Category', 'Installments', 'Total', 'Paid', 'Outstanding', 'Overdue'];
        $rows = $service->detail()->map(fn ($row) => [
            ($dueBasis ? $row['due_date'] : $row['expense_date'])?->format('Y-m-d'),
            $row['item'],
            $row['vendor'] ?? '',
            $row['project'] ?? '',
            $row['job_site'] ?? 'Project-level',
            $row['category'] ? ucfirst($row['category']) : '',
            $row['payment_label'],
            $this->money($row['total']),
            $this->money($row['paid']),
            $this->money($row['outstanding']),
            $this->money($row['overdue']),
        ])->all();

        $k = $service->kpis();
        $totals = ['Total', '', '', '', '', '', '', $this->money($k['total']), $this->money($k['paid']), $this->money($k['outstanding']), $this->money($k['overdue'])];

        return [$headers, $rows, $totals];
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
