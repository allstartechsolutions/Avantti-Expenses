<?php

namespace App\Livewire\Report;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\CompanyFinancialService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company financial position: money in and money out, settled and open,
 * across every source — income, invoices, expenses and contracts
 * (including their cronograma parcelas and medições).
 */
class CompanyFinancialReport extends Component
{
    use AuthorizesAbility;

    public string $clientFilter = '';

    public string $projectFilter = '';

    public string $jobSiteFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    /** all | in | out */
    public string $directionFilter = '';

    /** settled | open | overdue */
    public string $statusFilter = '';

    public string $sourceFilter = '';

    protected $queryString = [
        'clientFilter' => ['except' => ''],
        'projectFilter' => ['except' => ''],
        'jobSiteFilter' => ['except' => ''],
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
        'directionFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sourceFilter' => ['except' => ''],
    ];

    public function setAllTime(): void
    {
        $this->fromDate = '';
        $this->toDate = '';
    }

    public function setCurrentMonth(): void
    {
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->endOfMonth()->toDateString();
    }

    public function setNextMonth(): void
    {
        $this->fromDate = now()->addMonthNoOverflow()->startOfMonth()->toDateString();
        $this->toDate = now()->addMonthNoOverflow()->endOfMonth()->toDateString();
    }

    public function setNext3Months(): void
    {
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->addMonthsNoOverflow(2)->endOfMonth()->toDateString();
    }

    public function setThisYear(): void
    {
        $this->fromDate = now()->startOfYear()->toDateString();
        $this->toDate = now()->endOfYear()->toDateString();
    }

    public function updatedProjectFilter(): void
    {
        $this->jobSiteFilter = '';
    }

    protected function service(): CompanyFinancialService
    {
        return CompanyFinancialService::forFilters(
            $this->clientFilter !== '' ? (int) $this->clientFilter : null,
            $this->projectFilter !== '' ? (int) $this->projectFilter : null,
            $this->jobSiteFilter !== '' ? (int) $this->jobSiteFilter : null,
        )->between(
            $this->fromDate !== '' ? $this->fromDate : null,
            $this->toDate !== '' ? $this->toDate : null,
        );
    }

    /**
     * The detail rows only. The tiles, source table and timeline always
     * show the whole picture — narrowing the list must not silently change
     * the totals above it.
     */
    protected function filterItems(Collection $items): Collection
    {
        return $items
            ->when($this->directionFilter !== '', fn ($rows) => $rows->where('direction', $this->directionFilter))
            ->when($this->statusFilter !== '', fn ($rows) => $rows->where('status', $this->statusFilter))
            ->when($this->sourceFilter !== '', fn ($rows) => $rows->where('source', $this->sourceFilter))
            ->values();
    }

    public function getClientsProperty(): Collection
    {
        return Client::whereHas('projects')->orderBy('company_name')->get(['id', 'company_name']);
    }

    public function getProjectsProperty(): Collection
    {
        return Project::query()
            ->when($this->clientFilter, fn ($q) => $q->where('client_id', $this->clientFilter))
            ->orderBy('project_name')
            ->get(['id', 'project_name']);
    }

    public function getJobSitesProperty(): Collection
    {
        return JobSite::query()
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->orderBy('job_site_name')
            ->get(['id', 'job_site_name']);
    }

    public function exportCsv(): StreamedResponse
    {
        $data = $this->service()->build();
        $rows = $this->filterItems($data['items']);
        $range = ($this->fromDate || $this->toDate)
            ? '-'.($this->fromDate ?: 'start').'-to-'.($this->toDate ?: 'open')
            : '';
        $filename = 'company-financials'.$range.'-'.now()->format('Y-m-d').'.csv';

        return new StreamedResponse(function () use ($data, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $money = fn ($v) => number_format((float) $v, 2, '.', '');

            fputcsv($out, [__('Summary'), __('Settled'), __('Open'), __('Overdue'), __('Total')]);
            foreach (['in' => __('Money in'), 'out' => __('Money out')] as $key => $label) {
                fputcsv($out, [$label, $money($data[$key]['settled']), $money($data[$key]['open']), $money($data[$key]['overdue']), $money($data[$key]['total'])]);
            }
            fputcsv($out, [__('Net (cash)'), $money($data['net']['cash']), '', '', '']);
            fputcsv($out, [__('Net (forecast)'), $money($data['net']['forecast']), '', '', '']);

            fputcsv($out, []);
            fputcsv($out, [__('By source'), __('Direction'), __('Settled'), __('Open'), __('Overdue'), __('Total')]);
            foreach ($data['sources'] as $source) {
                fputcsv($out, [$source['label'], $source['direction'], $money($source['settled']), $money($source['open']), $money($source['overdue']), $money($source['total'])]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('Month'), __('In'), __('Out'), __('Net')]);
            foreach ($data['timeline']['months'] as $month) {
                fputcsv($out, [$month['label'], $money($month['in']), $money($month['out']), $money($month['net'])]);
            }
            if ($data['timeline']['undated']['in'] > 0 || $data['timeline']['undated']['out'] > 0) {
                fputcsv($out, [__('No due date'), $money($data['timeline']['undated']['in']), $money($data['timeline']['undated']['out']), $money($data['timeline']['undated']['net'])]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('Date'), __('Direction'), __('Source'), __('Party'), __('Project'), __('Job Site'), __('Description'), __('Status'), __('Amount')]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['date']?->format('Y-m-d') ?? '',
                    $row['direction'],
                    $row['source'],
                    $row['party'],
                    $row['project'],
                    $row['job_site'],
                    $row['description'],
                    $row['status'],
                    $money($row['amount']),
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function render()
    {
        $data = $this->service()->build();

        return view('livewire.report.company-financial-report', [
            'data' => $data,
            'rows' => $this->filterItems($data['items']),
            'clients' => $this->clients,
            'projects' => $this->projects,
            'jobSites' => $this->jobSites,
        ])->layout('components.layouts.app');
    }
}
