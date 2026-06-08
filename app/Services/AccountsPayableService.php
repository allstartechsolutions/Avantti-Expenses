<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Expense;
use App\Models\ExpensePayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the Accounts Payable report.
 *
 * Consolidates every "money the company pays out" stream:
 *   - Expenses + installment ExpensePayments (scheduled, by due date)
 *   - Subcontractor Contract payments (a ledger of payments made, by payment date)
 *
 * Expenses have scheduled due dates, so they drive the dated Due / Overdue /
 * Projection figures. Contracts have NO due dates (payments are recorded after
 * the fact), so contract payments only feed "Paid in Period" and their
 * outstanding balances are reported separately, point-in-time.
 *
 * Purchase Orders are NOT included: an approved PO becomes an Expense and is
 * counted there. Payment Batches are NOT included: they produce ContractPayment
 * records and are counted there.
 */
class AccountsPayableService
{
    protected Carbon $start;
    protected Carbon $end;
    protected Carbon $today;

    protected ?Collection $outstandingCache = null;

    public function __construct(
        string $fromDate,
        string $toDate,
        protected string $projectFilter = '',
        protected string $statusFilter = 'unpaid',
    ) {
        $this->start = Carbon::parse($fromDate)->startOfDay();
        $this->end = Carbon::parse($toDate)->endOfDay();
        $this->today = Carbon::now()->startOfDay();
    }

    // =========================================================================
    // QUERY HELPERS — each returns a fresh builder so it can be reused for both
    // the row list and the KPI totals.
    //
    // "Open" = not yet paid (parent not cancelled). Overdue is DERIVED from the
    // due date vs. today rather than relying on a stored 'overdue' status
    // (nothing marks payments overdue automatically).
    // =========================================================================

    protected function openInstallments()
    {
        return ExpensePayment::query()
            ->where('status', '!=', 'paid')
            ->whereHas('expense', function ($q) {
                $q->where('status', '!=', 'cancelled');
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            });
    }

    protected function paidInstallments()
    {
        return ExpensePayment::query()
            ->where('status', 'paid')
            ->whereHas('expense', function ($q) {
                $q->where('status', '!=', 'cancelled');
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            });
    }

    protected function openOneTime()
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter));
    }

    protected function paidOneTime()
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->where('status', 'paid')
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter));
    }

    protected function paidContractPayments()
    {
        return ContractPayment::query()
            ->with([
                'contract.project:id,project_name',
                'contract.jobSite:id,job_site_name',
                'contract.subcontractor:id,company_name',
            ])
            ->whereHas('contract', function ($q) {
                $q->where('status', '!=', 'cancelled');
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            });
    }

    // =========================================================================
    // ROW MAPPERS — normalize every source into one shape.
    // =========================================================================

    protected function mapInstallmentRow(ExpensePayment $p): array
    {
        $expense = $p->expense;
        $status = $p->status === 'paid'
            ? 'paid'
            : ($p->due_date && $p->due_date->lt($this->today) ? 'overdue' : 'pending');

        return [
            'source' => 'expense',
            'type' => 'installment',
            'due_date' => $p->due_date,
            'vendor' => $expense?->supplier?->name,
            'item' => $expense?->item_name . ' (#' . $p->payment_number . ')',
            'project' => $expense?->project?->project_name,
            'project_id' => $expense?->project_id,
            'job_site' => $expense?->jobSite?->job_site_name,
            'job_site_id' => $expense?->job_site_id,
            'status' => $status,
            'amount' => (float) $p->amount,
        ];
    }

    protected function mapOneTimeRow(Expense $e): array
    {
        // Fall back to the expense date when no due date was ever set.
        $due = $e->payment_due_date ?? $e->expense_date;
        $status = $e->status === 'paid'
            ? 'paid'
            : ($due && $due->lt($this->today) ? 'overdue' : 'pending');

        return [
            'source' => 'expense',
            'type' => 'one_time',
            'due_date' => $due,
            'vendor' => $e->supplier?->name,
            'item' => $e->item_name,
            'project' => $e->project?->project_name,
            'project_id' => $e->project_id,
            'job_site' => $e->jobSite?->job_site_name,
            'job_site_id' => $e->job_site_id,
            'status' => $e->status === 'unpaid' ? 'pending' : $e->status,
            'amount' => (float) $e->total_amount,
        ];
    }

    protected function mapContractPaymentRow(ContractPayment $p): array
    {
        $c = $p->contract;
        $label = __('Contract') . ' ' . ($c?->contract_number ?? '');
        if ($p->phase) {
            $label .= ' — ' . $p->phase;
        }

        return [
            'source' => 'contract',
            'type' => 'contract_payment',
            'due_date' => $p->payment_date,
            'vendor' => $c?->subcontractor?->company_name,
            'item' => trim($label),
            'project' => $c?->project?->project_name,
            'project_id' => $c?->project_id,
            'job_site' => $c?->jobSite?->job_site_name,
            'job_site_id' => $c?->job_site_id,
            'status' => 'paid',
            'amount' => (float) $p->amount,
        ];
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Unified row list for the selected period.
     * Open expense items are matched by due date; paid items (expense + contract)
     * by their paid/payment date.
     */
    public function rows(): Collection
    {
        $from = $this->start->toDateString();
        $to = $this->end->toDateString();
        $filter = $this->statusFilter;

        $wantPaid = in_array($filter, ['paid', 'all'], true);
        $wantOpen = in_array($filter, ['unpaid', 'pending', 'overdue', 'all'], true);

        $rows = collect();

        $instWith = ['expense.project:id,project_name', 'expense.jobSite:id,job_site_name', 'expense.supplier:id,name'];
        $oneWith = ['project:id,project_name', 'jobSite:id,job_site_name', 'supplier:id,name'];

        // ---- Open (unpaid) expense items, matched by DUE date ----
        if ($wantOpen) {
            $openInst = $this->openInstallments()
                ->with($instWith)
                ->whereBetween('due_date', [$from, $to])
                ->orderBy('due_date')
                ->get()
                ->map(fn (ExpensePayment $p) => $this->mapInstallmentRow($p));

            $openOne = $this->openOneTime()
                ->with($oneWith)
                ->whereRaw('COALESCE(payment_due_date, expense_date) BETWEEN ? AND ?', [$from, $to])
                ->orderByRaw('COALESCE(payment_due_date, expense_date)')
                ->get()
                ->map(fn (Expense $e) => $this->mapOneTimeRow($e));

            $rows = $rows->concat($openInst)->concat($openOne);

            // Narrow to a single derived status when requested.
            if ($filter === 'pending') {
                $rows = $rows->where('status', 'pending');
            } elseif ($filter === 'overdue') {
                $rows = $rows->where('status', 'overdue');
            }
        }

        // ---- Paid items (expense + contract), matched by PAID date ----
        if ($wantPaid) {
            $paidInst = $this->paidInstallments()
                ->with($instWith)
                ->whereRaw('COALESCE(paid_date, due_date) BETWEEN ? AND ?', [$from, $to])
                ->orderByRaw('COALESCE(paid_date, due_date)')
                ->get()
                ->map(fn (ExpensePayment $p) => $this->mapInstallmentRow($p));

            $paidOne = $this->paidOneTime()
                ->with($oneWith)
                ->whereRaw('COALESCE(paid_date, expense_date) BETWEEN ? AND ?', [$from, $to])
                ->orderByRaw('COALESCE(paid_date, expense_date)')
                ->get()
                ->map(fn (Expense $e) => $this->mapOneTimeRow($e));

            $contractPaid = $this->paidContractPayments()
                ->whereBetween('payment_date', [$from, $to])
                ->orderBy('payment_date')
                ->get()
                ->map(fn (ContractPayment $p) => $this->mapContractPaymentRow($p));

            $rows = $rows->concat($paidInst)->concat($paidOne)->concat($contractPaid);
        }

        return $rows->sortBy('due_date')->values();
    }

    /**
     * Summary KPI cards — independent of the row status filter.
     */
    public function kpis(): array
    {
        $from = $this->start->toDateString();
        $to = $this->end->toDateString();
        $today = $this->today->toDateString();

        // Due in period — expense open items, by due date.
        $dueInstSum = $this->openInstallments()->whereBetween('due_date', [$from, $to])->sum('amount');
        $dueInstCount = $this->openInstallments()->whereBetween('due_date', [$from, $to])->count();
        $dueOne = $this->openOneTime()
            ->whereRaw('COALESCE(payment_due_date, expense_date) BETWEEN ? AND ?', [$from, $to])
            ->get();
        $totalDue = round(($dueInstSum / 100) + $dueOne->sum('total_amount'), 2);
        $countDue = $dueInstCount + $dueOne->count();

        // Paid in period — expense paid + contract payments, by paid/payment date.
        $paidInstSum = $this->paidInstallments()
            ->whereRaw('COALESCE(paid_date, due_date) BETWEEN ? AND ?', [$from, $to])
            ->sum('amount');
        $paidOne = $this->paidOneTime()
            ->whereRaw('COALESCE(paid_date, expense_date) BETWEEN ? AND ?', [$from, $to])
            ->get();
        $contractPaidSum = $this->paidContractPayments()
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');
        $totalPaid = round(($paidInstSum / 100) + $paidOne->sum('total_amount') + ($contractPaidSum / 100), 2);

        // Overdue as of today — expense open items past due, regardless of period.
        $overdueInstSum = $this->openInstallments()->whereDate('due_date', '<', $today)->sum('amount');
        $overdueOne = $this->openOneTime()
            ->whereRaw('COALESCE(payment_due_date, expense_date) < ?', [$today])
            ->get();
        $overdueTotal = round(($overdueInstSum / 100) + $overdueOne->sum('total_amount'), 2);

        // Contract balances outstanding — point in time (date range does not apply).
        $outstanding = $this->outstandingContracts();

        return [
            'total_due' => $totalDue,
            'count_due' => $countDue,
            'total_paid' => $totalPaid,
            'overdue_total' => $overdueTotal,
            'contract_outstanding_total' => round($outstanding->sum('balance'), 2),
            'contract_outstanding_count' => $outstanding->count(),
        ];
    }

    /**
     * Subcontractor contracts with an outstanding balance (point-in-time).
     * Not tied to the date range — like the Overdue KPI, it reflects "as of now".
     */
    public function outstandingContracts(): Collection
    {
        return $this->outstandingCache ??= Contract::query()
            ->with([
                'project:id,project_name',
                'jobSite:id,job_site_name',
                'subcontractor:id,company_name',
            ])
            ->where('status', '!=', 'cancelled')
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->get()
            ->map(function (Contract $c) {
                return [
                    'contract_number' => $c->contract_number,
                    'subcontractor' => $c->subcontractor?->company_name,
                    'project' => $c->project?->project_name,
                    'project_id' => $c->project_id,
                    'job_site' => $c->jobSite?->job_site_name,
                    'job_site_id' => $c->job_site_id,
                    'status' => $c->status,
                    'adjusted_amount' => $c->getAdjustedAmount(),
                    'paid' => $c->getAmountPaid(),
                    'balance' => $c->getBalanceDue(),
                ];
            })
            ->filter(fn ($r) => $r['balance'] > 0)
            ->sortByDesc('balance')
            ->values();
    }

    /**
     * Forward-looking 12-month projection, starting the month AFTER the period.
     * Expenses only — contracts have no payment schedule to project from.
     */
    public function projections(): array
    {
        $start = (clone $this->end)->endOfMonth()->addDay()->startOfMonth();
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $monthStart = (clone $start)->addMonths($i);
            $monthEnd = (clone $monthStart)->endOfMonth();
            $from = $monthStart->toDateString();
            $to = $monthEnd->toDateString();

            $installmentSum = $this->openInstallments()->whereBetween('due_date', [$from, $to])->sum('amount');
            $installmentCount = $this->openInstallments()->whereBetween('due_date', [$from, $to])->count();

            $oneTimeRows = $this->openOneTime()
                ->whereRaw('COALESCE(payment_due_date, expense_date) BETWEEN ? AND ?', [$from, $to])
                ->get();

            $months[] = [
                'month' => $monthStart->translatedFormat('M Y'),
                'count' => $installmentCount + $oneTimeRows->count(),
                'amount' => round(($installmentSum / 100) + $oneTimeRows->sum('total_amount'), 2),
            ];
        }

        return $months;
    }
}
