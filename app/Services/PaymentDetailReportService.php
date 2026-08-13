<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Expense;
use App\Models\ExpensePayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the Payment Details report.
 *
 * Where the Expense Report shows whole-expense figures ("where did the money
 * go") and the Payment Schedule shows aggregates, this report lists ONE ROW
 * PER PAYMENT so period totals are true period amounts:
 *
 *   - each expense installment individually (its own due date and amount)
 *   - one-time expenses (due date = COALESCE(payment_due_date, expense_date))
 *   - contract payments already made (dated by payment date)
 *   - open contract balances (dated by the contract's end date; contracts
 *     have no payment schedule, so the end date is the best available
 *     placement — balances without an end date are always shown, undated)
 *
 * Date range semantics (matching the Accounts Payable report): open rows are
 * matched by due date, paid rows by paid date. Overdue is DERIVED from the
 * row date vs. today, never from a stored status. Cancelled expenses and
 * contracts are excluded.
 */
class PaymentDetailReportService
{
    protected Carbon $start;
    protected Carbon $end;
    protected Carbon $today;

    protected ?Collection $cache = null;

    public function __construct(
        string $fromDate,
        string $toDate,
        protected string $projectFilter = '',
        protected string $jobSiteFilter = '',
        protected string $vendorFilter = '',
        protected string $subcontractorFilter = '',
        protected string $clientFilter = '',
        protected string|array $statusFilter = 'all',
        protected string $typeFilter = 'all',
    ) {
        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();

        // Swap reversed bounds instead of returning an empty report.
        if ($start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $this->start = $start;
        $this->end = $end;
        $this->today = Carbon::now()->startOfDay();
    }

    // =========================================================================
    // BASE DATA — loaded once, normalized, and cached.
    // =========================================================================

    /**
     * All payment rows matching the filters, sorted by date (undated contract
     * balances last).
     */
    public function rows(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = collect();

        if ($this->includesExpenses()) {
            $rows = $rows
                ->concat($this->installmentRows())
                ->concat($this->oneTimeRows());
        }

        if ($this->includesContracts()) {
            $rows = $rows
                ->concat($this->contractPaymentRows())
                ->concat($this->contractBalanceRows());
        }

        return $this->cache = $rows
            ->filter(fn (array $r) => $this->passesStatus($r))
            ->sortBy(fn (array $r) => $r['date']?->format('Y-m-d') ?? '9999-12-31')
            ->values();
    }

    protected function includesExpenses(): bool
    {
        // A vendor (supplier) filter can only match expenses; a subcontractor
        // filter can only match contracts.
        return $this->typeFilter !== 'contracts' && $this->subcontractorFilter === '';
    }

    protected function includesContracts(): bool
    {
        return $this->typeFilter !== 'expenses' && $this->vendorFilter === '';
    }

    /**
     * Accepts one status, several, or none: a string or array of
     * paid / pending / overdue. Anything else ('all', empty array,
     * legacy values) means no status restriction.
     */
    protected function passesStatus(array $row): bool
    {
        $statuses = array_values(array_intersect(
            (array) $this->statusFilter,
            ['paid', 'pending', 'overdue']
        ));

        return $statuses === [] || in_array($row['status'], $statuses, true);
    }

    // =========================================================================
    // EXPENSE ROWS
    // =========================================================================

    protected function expenseScope($query): void
    {
        $query->where('status', '!=', 'cancelled')
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->jobSiteFilter, fn ($q) => $q->where('job_site_id', $this->jobSiteFilter))
            ->when($this->vendorFilter, fn ($q) => $q->where('supplier_id', $this->vendorFilter))
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)));
    }

    protected function installmentRows(): Collection
    {
        $from = $this->start->toDateString();
        $to = $this->end->toDateString();

        return ExpensePayment::query()
            ->whereHas('expense', fn ($q) => $this->expenseScope($q))
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($q) use ($from, $to) {
                    $q->where('status', 'paid')
                        ->whereRaw('COALESCE(paid_date, due_date) BETWEEN ? AND ?', [$from, $to]);
                })->orWhere(function ($q) use ($from, $to) {
                    $q->where('status', '!=', 'paid')
                        ->whereBetween('due_date', [$from, $to]);
                });
            })
            ->with([
                'expense.project:id,project_name',
                'expense.jobSite:id,job_site_name',
                'expense.supplier:id,name',
                'paidBy:id,name',
            ])
            ->get()
            ->map(function (ExpensePayment $p) {
                $e = $p->expense;
                $isPaid = $p->status === 'paid';
                $date = $isPaid ? ($p->paid_date ?? $p->due_date) : $p->due_date;

                return [
                    'date' => $date,
                    'due_date' => $p->due_date,
                    'paid_date' => $p->paid_date,
                    'type' => 'expense',
                    'vendor' => $e?->supplier?->name,
                    'item' => $e?->item_name,
                    'project' => $e?->project?->project_name,
                    'project_id' => $e?->project_id,
                    'job_site' => $e?->jobSite?->job_site_name,
                    'installment_label' => $p->payment_number . '/' . $e?->total_installments,
                    'status' => $this->deriveStatus($isPaid, $p->due_date),
                    'paid_by' => $p->paidBy?->name,
                    'amount' => (float) $p->amount,
                ];
            });
    }

    protected function oneTimeRows(): Collection
    {
        $from = $this->start->toDateString();
        $to = $this->end->toDateString();

        return Expense::query()
            ->where('total_installments', 1)
            ->tap(fn ($q) => $this->expenseScope($q))
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($q) use ($from, $to) {
                    $q->where('status', 'paid')
                        ->whereRaw('COALESCE(paid_date, expense_date) BETWEEN ? AND ?', [$from, $to]);
                })->orWhere(function ($q) use ($from, $to) {
                    $q->where('status', '!=', 'paid')
                        ->whereRaw('COALESCE(payment_due_date, expense_date) BETWEEN ? AND ?', [$from, $to]);
                });
            })
            ->with([
                'project:id,project_name',
                'jobSite:id,job_site_name',
                'supplier:id,name',
                'paidBy:id,name',
            ])
            ->get()
            ->map(function (Expense $e) {
                $isPaid = $e->status === 'paid';
                $due = $e->payment_due_date ?? $e->expense_date;
                $date = $isPaid ? ($e->paid_date ?? $e->expense_date) : $due;

                return [
                    'date' => $date,
                    'due_date' => $due,
                    'paid_date' => $e->paid_date,
                    'type' => 'expense',
                    'vendor' => $e->supplier?->name,
                    'item' => $e->item_name,
                    'project' => $e->project?->project_name,
                    'project_id' => $e->project_id,
                    'job_site' => $e->jobSite?->job_site_name,
                    'installment_label' => '1x',
                    'status' => $this->deriveStatus($isPaid, $due),
                    'paid_by' => $e->paidBy?->name,
                    'amount' => (float) $e->total_amount,
                ];
            });
    }

    // =========================================================================
    // CONTRACT ROWS
    // =========================================================================

    protected function contractScope($query): void
    {
        $query->where('status', '!=', 'cancelled')
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->jobSiteFilter, fn ($q) => $q->where('job_site_id', $this->jobSiteFilter))
            ->when($this->subcontractorFilter, fn ($q) => $q->where('subcontractor_id', $this->subcontractorFilter))
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)));
    }

    protected function contractPaymentRows(): Collection
    {
        $from = $this->start->toDateString();
        $to = $this->end->toDateString();

        return ContractPayment::query()
            ->whereBetween('payment_date', [$from, $to])
            ->whereHas('contract', fn ($q) => $this->contractScope($q))
            ->with([
                'contract.subcontractor:id,name',
                'contract.project:id,project_name',
                'contract.jobSite:id,job_site_name',
                'createdBy:id,name',
            ])
            ->get()
            ->map(function (ContractPayment $p) {
                $c = $p->contract;

                return [
                    'date' => $p->payment_date,
                    'due_date' => null,
                    'paid_date' => $p->payment_date,
                    'type' => 'contract',
                    'vendor' => $c?->subcontractor?->company_name,
                    'item' => trim(($c?->contract_number ?? '') . ' ' . __('Payment')),
                    'project' => $c?->project?->project_name,
                    'project_id' => $c?->project_id,
                    'job_site' => $c?->jobSite?->job_site_name,
                    'installment_label' => null,
                    'status' => 'paid',
                    'paid_by' => $p->createdBy?->name,
                    'amount' => (float) $p->amount,
                ];
            });
    }

    /**
     * Open contract balances, dated by the contract's end date. Balances with
     * no end date are always included (undated) so nothing is hidden.
     */
    protected function contractBalanceRows(): Collection
    {
        $from = $this->start->toDateString();
        $to = $this->end->toDateString();

        return Contract::query()
            ->tap(fn ($q) => $this->contractScope($q))
            ->where(function ($q) use ($from, $to) {
                $q->whereNull('end_date')->orWhereBetween('end_date', [$from, $to]);
            })
            ->with([
                'subcontractor:id,name',
                'project:id,project_name',
                'jobSite:id,job_site_name',
            ])
            ->get()
            ->map(function (Contract $c) {
                $balance = $c->getBalanceDue();

                if (round($balance, 2) <= 0) {
                    return null;
                }

                return [
                    'date' => $c->end_date,
                    'due_date' => $c->end_date,
                    'paid_date' => null,
                    'type' => 'contract',
                    'vendor' => $c->subcontractor?->company_name,
                    'item' => trim(($c->contract_number ?? '') . ' ' . __('Balance')),
                    'project' => $c->project?->project_name,
                    'project_id' => $c->project_id,
                    'job_site' => $c->jobSite?->job_site_name,
                    'installment_label' => null,
                    'status' => $this->deriveStatus(false, $c->end_date),
                    'paid_by' => null,
                    'amount' => $balance,
                ];
            })
            ->filter()
            ->values();
    }

    protected function deriveStatus(bool $isPaid, ?Carbon $due): string
    {
        if ($isPaid) {
            return 'paid';
        }

        return $due && $due->lt($this->today) ? 'overdue' : 'pending';
    }

    // =========================================================================
    // AGGREGATIONS
    // =========================================================================

    public function kpis(): array
    {
        $rows = $this->rows();

        return [
            'count' => $rows->count(),
            'total' => round($rows->sum('amount'), 2),
            'paid' => round($rows->where('status', 'paid')->sum('amount'), 2),
            'pending' => round($rows->where('status', 'pending')->sum('amount'), 2),
            'overdue' => round($rows->where('status', 'overdue')->sum('amount'), 2),
        ];
    }

    /**
     * Rows grouped by project, each with job-site sub-rows.
     */
    public function byProject(): Collection
    {
        return $this->rows()
            ->groupBy(fn (array $r) => $r['project'] ?? '—')
            ->map(function (Collection $rows, string $project) {
                return [
                    'project' => $project,
                    'count' => $rows->count(),
                    'total' => round($rows->sum('amount'), 2),
                    'paid' => round($rows->where('status', 'paid')->sum('amount'), 2),
                    'pending' => round($rows->where('status', 'pending')->sum('amount'), 2),
                    'overdue' => round($rows->where('status', 'overdue')->sum('amount'), 2),
                    'jobsites' => $rows
                        ->groupBy(fn (array $r) => $r['job_site'] ?? '')
                        ->map(fn (Collection $js, string $name) => [
                            'job_site' => $name !== '' ? $name : null,
                            'count' => $js->count(),
                            'total' => round($js->sum('amount'), 2),
                            'paid' => round($js->where('status', 'paid')->sum('amount'), 2),
                            'pending' => round($js->where('status', 'pending')->sum('amount'), 2),
                            'overdue' => round($js->where('status', 'overdue')->sum('amount'), 2),
                        ])
                        ->sortByDesc('total')
                        ->values(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Rows grouped by payee (supplier or subcontractor).
     */
    public function byVendor(): Collection
    {
        return $this->rows()
            ->groupBy(fn (array $r) => ($r['type'] === 'contract' ? 'c:' : 'e:') . ($r['vendor'] ?? ''))
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'vendor' => $first['vendor'],
                    'type' => $first['type'],
                    'count' => $rows->count(),
                    'total' => round($rows->sum('amount'), 2),
                    'paid' => round($rows->where('status', 'paid')->sum('amount'), 2),
                    'pending' => round($rows->where('status', 'pending')->sum('amount'), 2),
                    'overdue' => round($rows->where('status', 'overdue')->sum('amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
