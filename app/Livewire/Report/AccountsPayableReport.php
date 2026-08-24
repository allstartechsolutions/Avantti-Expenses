<?php

namespace App\Livewire\Report;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Client;
use App\Models\Project;
use App\Services\AccountsPayableService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountsPayableReport extends Component
{
    use AuthorizesAbility;

    public string $fromDate = '';
    public string $toDate = '';
    public string $projectFilter = '';
    public string $clientFilter = '';
    public string $statusFilter = 'unpaid';
    public bool $showZeroBalance = false;

    protected $queryString = [
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
        'projectFilter' => ['except' => ''],
        'clientFilter' => ['except' => ''],
        'statusFilter' => ['except' => 'unpaid'],
        'showZeroBalance' => ['except' => false],
    ];

    public function mount(): void
    {
        $this->authorizeAbility('reports.accounts_payable');

        if ($this->fromDate === '') {
            $this->fromDate = Carbon::now()->startOfMonth()->toDateString();
        }
        if ($this->toDate === '') {
            $this->toDate = Carbon::now()->endOfMonth()->toDateString();
        }
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

    public function setCurrentQuarter(): void
    {
        $this->fromDate = Carbon::now()->firstOfQuarter()->toDateString();
        $this->toDate = Carbon::now()->lastOfQuarter()->toDateString();
    }

    public function setYearToDate(): void
    {
        $this->fromDate = Carbon::now()->startOfYear()->toDateString();
        $this->toDate = Carbon::now()->endOfYear()->toDateString();
    }

    protected function service(): AccountsPayableService
    {
        return new AccountsPayableService(
            $this->fromDate,
            $this->toDate,
            $this->projectFilter,
            $this->statusFilter,
            $this->clientFilter,
        );
    }

    public function getSelectedPeriodRowsProperty(): Collection
    {
        return $this->service()->rows();
    }

    public function getKpisProperty(): array
    {
        return $this->service()->kpis();
    }

    public function getSubcontractorSummaryProperty(): Collection
    {
        return $this->service()->subcontractorSummary()
            ->unless($this->showZeroBalance, fn (Collection $rows) => $rows->filter(
                fn (array $row) => round($row['outstanding'], 2) != 0.0
            )->values());
    }

    public function getProjectsProperty()
    {
        return Project::orderBy('project_name')->get(['id', 'project_name']);
    }

    public function getClientsProperty()
    {
        return Client::whereHas('projects')
            ->orderBy('company_name')
            ->get(['id', 'company_name']);
    }

    public function getProjectionsProperty(): array
    {
        return $this->service()->projections();
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->selectedPeriodRows;
        $filename = 'accounts-payable-' . $this->fromDate . '-to-' . $this->toDate . '.csv';

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel handles UTF-8 correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Due Date'), __('Vendor'), __('Item'), __('Project'), __('Job Site'), __('Status'), __('Amount')]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['due_date']?->format('Y-m-d'),
                    $row['vendor'] ?? '',
                    $row['item'] ?? '',
                    $row['project'] ?? '',
                    $row['job_site'] ?? __('Project-level'),
                    \App\Models\ExpensePayment::statusLabel($row['status']),
                    number_format($row['amount'], 2, '.', ''),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('Total'), '', '', '', '', '', number_format($rows->sum('amount'), 2, '.', '')]);

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function render()
    {
        return view('livewire.report.accounts-payable-report', [
            'rows' => $this->selectedPeriodRows,
            'kpis' => $this->kpis,
            'projects' => $this->projects,
            'clients' => $this->clients,
            'projections' => $this->projections,
            'subcontractorSummary' => $this->subcontractorSummary,
        ])->layout('components.layouts.app');
    }
}
