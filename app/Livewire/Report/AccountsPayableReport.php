<?php

namespace App\Livewire\Report;

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountsPayableReport extends Component
{
    public string $fromDate = '';
    public string $toDate = '';
    public string $projectFilter = '';
    public string $statusFilter = 'unpaid';

    protected $queryString = [
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
        'projectFilter' => ['except' => ''],
        'statusFilter' => ['except' => 'unpaid'],
    ];

    public function mount(): void
    {
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

    protected function statusFilterValues(): array
    {
        return match ($this->statusFilter) {
            'pending' => ['pending'],
            'overdue' => ['overdue'],
            'paid' => ['paid'],
            'unpaid' => ['pending', 'overdue'],
            default => ['pending', 'overdue', 'paid'],
        };
    }

    /**
     * Build the unified row list (installments + one-time) for a given date range.
     */
    protected function rowsForRange(Carbon $start, Carbon $end): Collection
    {
        $statuses = $this->statusFilterValues();

        // Installment payments
        $installments = ExpensePayment::query()
            ->with(['expense.project:id,project_name', 'expense.jobSite:id,job_site_name', 'expense.supplier:id,name'])
            ->whereIn('status', $statuses)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('expense', function ($q) {
                $q->where('status', '!=', 'cancelled');
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            })
            ->orderBy('due_date')
            ->get()
            ->map(function (ExpensePayment $p) {
                $expense = $p->expense;
                return [
                    'type' => 'installment',
                    'due_date' => $p->due_date,
                    'vendor' => $expense?->supplier?->name,
                    'item' => $expense?->item_name . ' (#' . $p->payment_number . ')',
                    'project' => $expense?->project?->project_name,
                    'job_site' => $expense?->jobSite?->job_site_name,
                    'status' => $p->status,
                    'amount' => (float) $p->amount,
                ];
            });

        // One-time expenses (single payment, not in expense_payments table)
        $oneTimeStatuses = array_intersect($statuses, ['pending', 'overdue', 'paid']);
        // Expense.status uses 'unpaid' rather than 'pending' for one-timers
        $expenseStatusMap = [];
        foreach ($oneTimeStatuses as $s) {
            $expenseStatusMap[] = $s === 'pending' ? 'unpaid' : $s;
        }
        $expenseStatusMap = array_values(array_unique($expenseStatusMap));

        $oneTime = Expense::query()
            ->with(['project:id,project_name', 'jobSite:id,job_site_name', 'supplier:id,name'])
            ->where('total_installments', 1)
            ->where('status', '!=', 'cancelled')
            ->whereIn('status', $expenseStatusMap)
            ->whereBetween('payment_due_date', [$start->toDateString(), $end->toDateString()])
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->orderBy('payment_due_date')
            ->get()
            ->map(function (Expense $e) {
                return [
                    'type' => 'one_time',
                    'due_date' => $e->payment_due_date,
                    'vendor' => $e->supplier?->name,
                    'item' => $e->item_name,
                    'project' => $e->project?->project_name,
                    'job_site' => $e->jobSite?->job_site_name,
                    'status' => $e->status === 'unpaid' ? 'pending' : $e->status,
                    'amount' => (float) $e->total_amount,
                ];
            });

        return $installments->concat($oneTime)->sortBy('due_date')->values();
    }

    public function getSelectedPeriodRowsProperty(): Collection
    {
        $start = Carbon::parse($this->fromDate)->startOfDay();
        $end = Carbon::parse($this->toDate)->endOfDay();

        return $this->rowsForRange($start, $end);
    }

    public function getKpisProperty(): array
    {
        $selected = $this->selectedPeriodRows;

        $totalDue = $selected->where('status', '!=', 'paid')->sum('amount');
        $totalPaid = $selected->where('status', 'paid')->sum('amount');
        $countDue = $selected->where('status', '!=', 'paid')->count();

        // Overdue as of today regardless of selected period
        $today = Carbon::now()->startOfDay();
        $overdueInstallments = ExpensePayment::where('status', 'overdue')
            ->whereHas('expense', function ($q) {
                $q->where('status', '!=', 'cancelled');
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            })
            ->sum('amount');

        $overdueOneTime = Expense::where('total_installments', 1)
            ->where('status', 'overdue')
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->get()
            ->sum('total_amount');

        $overdueTotal = round(($overdueInstallments / 100) + $overdueOneTime, 2);

        return [
            'total_due' => round($totalDue, 2),
            'count_due' => $countDue,
            'total_paid' => round($totalPaid, 2),
            'overdue_total' => $overdueTotal,
        ];
    }

    public function getProjectsProperty()
    {
        return Project::orderBy('project_name')->get(['id', 'project_name']);
    }

    /**
     * Forward-looking 12-month projection, starting the month AFTER toDate.
     * Uses 'unpaid' (pending + overdue) regardless of the user's status filter,
     * since projections are about what's expected to be paid.
     */
    public function getProjectionsProperty(): array
    {
        $start = Carbon::parse($this->toDate)->endOfMonth()->addDay()->startOfMonth();
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $monthStart = (clone $start)->addMonths($i);
            $monthEnd = (clone $monthStart)->endOfMonth();

            // Installments
            $installmentSum = ExpensePayment::query()
                ->whereIn('status', ['pending', 'overdue'])
                ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereHas('expense', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                    if ($this->projectFilter) {
                        $q->where('project_id', $this->projectFilter);
                    }
                })
                ->sum('amount');
            $installmentCount = ExpensePayment::query()
                ->whereIn('status', ['pending', 'overdue'])
                ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereHas('expense', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                    if ($this->projectFilter) {
                        $q->where('project_id', $this->projectFilter);
                    }
                })
                ->count();

            // One-time
            $oneTimeRows = Expense::query()
                ->where('total_installments', 1)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->whereBetween('payment_due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
                ->get();
            $oneTimeSum = $oneTimeRows->sum('total_amount');
            $oneTimeCount = $oneTimeRows->count();

            $months[] = [
                'month' => $monthStart->translatedFormat('M Y'),
                'count' => $installmentCount + $oneTimeCount,
                'amount' => round(($installmentSum / 100) + $oneTimeSum, 2),
            ];
        }

        return $months;
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->selectedPeriodRows;
        $filename = 'accounts-payable-' . $this->fromDate . '-to-' . $this->toDate . '.csv';

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel handles UTF-8 correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Due Date', 'Vendor', 'Item', 'Project', 'Job Site', 'Status', 'Amount']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['due_date']?->format('Y-m-d'),
                    $row['vendor'] ?? '',
                    $row['item'] ?? '',
                    $row['project'] ?? '',
                    $row['job_site'] ?? 'Project-level',
                    ucfirst($row['status']),
                    number_format($row['amount'], 2, '.', ''),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Total', '', '', '', '', '', number_format($rows->sum('amount'), 2, '.', '')]);

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
            'projections' => $this->projections,
        ])->layout('components.layouts.app');
    }
}
