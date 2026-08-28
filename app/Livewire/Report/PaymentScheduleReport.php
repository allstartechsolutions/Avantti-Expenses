<?php

namespace App\Livewire\Report;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\PaymentScheduleService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentScheduleReport extends Component
{
    use AuthorizesAbility;

    public string $clientFilter = '';
    public string $projectFilter = '';
    public string $jobSiteFilter = '';
    public string $fromDate = '';
    public string $toDate = '';

    protected $queryString = [
        'clientFilter' => ['except' => ''],
        'projectFilter' => ['except' => ''],
        'jobSiteFilter' => ['except' => ''],
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
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

    /**
     * Job site list depends on the selected project, so clear a stale selection
     * when the project changes.
     */
    public function updatedProjectFilter(): void
    {
        $this->jobSiteFilter = '';
    }

    protected function service(): PaymentScheduleService
    {
        return PaymentScheduleService::forSystem(
            $this->clientFilter !== '' ? (int) $this->clientFilter : null,
            $this->projectFilter !== '' ? (int) $this->projectFilter : null,
            $this->jobSiteFilter !== '' ? (int) $this->jobSiteFilter : null,
        )->between(
            $this->fromDate !== '' ? $this->fromDate : null,
            $this->toDate !== '' ? $this->toDate : null,
        );
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

    /**
     * Export the summary and monthly projection as CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        // Reading a figure on screen and walking out with the file are two
        // different acts: the view grant answers the first, this one the
        // second. Declared in the catalogue from the start; nothing
        // enforced it until now.
        $this->authorizeAbility('reports.export');

        $schedule = $this->service()->build();
        $range = ($this->fromDate || $this->toDate)
            ? '-' . ($this->fromDate ?: 'start') . '-to-' . ($this->toDate ?: 'open')
            : '';
        $filename = 'payment-schedule' . $range . '-' . now()->format('Y-m-d') . '.csv';

        return new StreamedResponse(function () use ($schedule) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $money = fn ($v) => number_format((float) $v, 2, '.', '');

            fputcsv($out, [__('Summary'), __('Committed'), __('Paid'), __('Outstanding')]);
            fputcsv($out, [__('Expenses'), $money($schedule['combined']['expenses']['total']), $money($schedule['combined']['expenses']['paid']), $money($schedule['combined']['expenses']['outstanding'])]);
            fputcsv($out, [__('Contracts'), $money($schedule['combined']['contracts']['total']), $money($schedule['combined']['contracts']['paid']), $money($schedule['combined']['contracts']['outstanding'])]);
            fputcsv($out, [__('Total'), $money($schedule['combined']['committed']), $money($schedule['combined']['paid']), $money($schedule['combined']['outstanding'])]);

            fputcsv($out, []);
            fputcsv($out, [__('Month'), __('Payments Due'), __('Amount Due')]);
            foreach ($schedule['projection']['buckets'] as $bucket) {
                fputcsv($out, [$bucket['label'], $bucket['count'], $money($bucket['amount'])]);
            }
            fputcsv($out, [__('Total Open'), $schedule['projection']['total_count'], $money($schedule['projection']['total_open'])]);

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function render()
    {
        return view('livewire.report.payment-schedule-report', [
            'schedule' => $this->service()->build(),
            'clients' => $this->clients,
            'projects' => $this->projects,
            'jobSites' => $this->jobSites,
        ])->layout('components.layouts.app');
    }
}
