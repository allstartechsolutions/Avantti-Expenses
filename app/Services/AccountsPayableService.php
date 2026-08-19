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
 *   - Open subcontractor contract money, scheduled by the cronograma
 *
 * Both expenses and contracts are dated, so both drive the Due / Overdue /
 * Projection figures. Contract money is dated by Contract::openPayableItems():
 * each open parcela on its own date, and whatever the cronograma does not
 * cover on the contract's END DATE. Contract money with no date at all (no
 * parcela date and no end date) cannot be matched by a period, so it shows
 * only in the point-in-time "Contract Balances Outstanding" figures.
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

    protected ?Collection $openContractCache = null;

    public function __construct(
        string $fromDate,
        string $toDate,
        protected string $projectFilter = '',
        protected string $statusFilter = 'unpaid',
        protected string $clientFilter = '',
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
                $this->applyClientScope($q);
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
                $this->applyClientScope($q);
            });
    }

    protected function openOneTime()
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->tap(fn ($q) => $this->applyClientScope($q));
    }

    protected function paidOneTime()
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->where('status', 'paid')
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->tap(fn ($q) => $this->applyClientScope($q));
    }

    /**
     * Constrain a query whose model belongs to a Project (Expense or Contract)
     * to a single client, since payables link to clients through their project.
     */
    protected function applyClientScope($q): void
    {
        if ($this->clientFilter) {
            $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter));
        }
    }

    protected function paidContractPayments()
    {
        return ContractPayment::query()
            ->with([
                'contract.project:id,project_name',
                'contract.jobSite:id,job_site_name',
                'contract.subcontractor:id,name',
            ])
            ->whereHas('contract', function ($q) {
                $q->where('status', '!=', 'cancelled');
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
                $this->applyClientScope($q);
            });
    }

    /**
     * Open contract money as dated rows, in the same shape as the expense
     * rows. Built once per instance and reused by rows(), kpis() and
     * projections(); undated items are kept here (callers that work on a
     * period filter them out by due_date).
     */
    protected function openContractItems(): Collection
    {
        return $this->openContractCache ??= Contract::query()
            ->with([
                'project:id,project_name',
                'jobSite:id,job_site_name',
                'subcontractor:id,name',
                'changeOrders',
                'payments',
                'scheduleItems.payments',
                'scheduleItems.measurements.payments',
            ])
            ->committed()
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->tap(fn ($q) => $this->applyClientScope($q))
            ->get()
            ->flatMap(fn (Contract $c) => array_map(
                fn (array $item) => $this->mapContractOpenRow($c, $item),
                $c->openPayableItems()
            ))
            ->values();
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

    /**
     * One open item of a contract's cronograma (or its unscheduled
     * remainder). Overdue is derived from the item's own due date, the
     * same rule the expense rows use.
     */
    protected function mapContractOpenRow(Contract $c, array $item): array
    {
        return [
            'source' => 'contract',
            'type' => 'contract_installment',
            'due_date' => $item['date'],
            'vendor' => $c->subcontractor?->company_name,
            'item' => trim(__('Contract') . ' ' . $c->contract_number . ' — ' . $item['label']),
            'project' => $c->project?->project_name,
            'project_id' => $c->project_id,
            'job_site' => $c->jobSite?->job_site_name,
            'job_site_id' => $c->job_site_id,
            'status' => $item['date'] && $item['date']->lt($this->today) ? 'overdue' : 'pending',
            'amount' => $item['amount'],
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

            // Open contract money, by the same due-date rule.
            $openContract = $this->openContractItems()
                ->filter(fn (array $row) => $row['due_date']
                    && $row['due_date']->betweenIncluded($this->start, $this->end));

            $rows = $rows->concat($openInst)->concat($openOne)->concat($openContract);

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
        // Due in period — contract parcelas and unscheduled remainders too.
        $dueContract = $this->openContractItems()
            ->filter(fn (array $row) => $row['due_date']
                && $row['due_date']->betweenIncluded($this->start, $this->end));

        $totalDue = round(($dueInstSum / 100) + $dueOne->sum('total_amount') + $dueContract->sum('amount'), 2);
        $countDue = $dueInstCount + $dueOne->count() + $dueContract->count();
        $dueExpenses = round(($dueInstSum / 100) + $dueOne->sum('total_amount'), 2);
        $dueContracts = round($dueContract->sum('amount'), 2);

        // Paid in period — split by source, by paid/payment date.
        $paidInstSum = $this->paidInstallments()
            ->whereRaw('COALESCE(paid_date, due_date) BETWEEN ? AND ?', [$from, $to])
            ->sum('amount');
        $paidOne = $this->paidOneTime()
            ->whereRaw('COALESCE(paid_date, expense_date) BETWEEN ? AND ?', [$from, $to])
            ->get();
        $contractPaidSum = $this->paidContractPayments()
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');

        $paidExpenses = round(($paidInstSum / 100) + $paidOne->sum('total_amount'), 2);
        $paidSubcontractors = round($contractPaidSum / 100, 2);
        $totalPaid = round($paidExpenses + $paidSubcontractors, 2);

        // Overdue as of today — expense open items past due, regardless of period.
        $overdueInstSum = $this->openInstallments()->whereDate('due_date', '<', $today)->sum('amount');
        $overdueOne = $this->openOneTime()
            ->whereRaw('COALESCE(payment_due_date, expense_date) < ?', [$today])
            ->get();
        $overdueContract = $this->openContractItems()
            ->filter(fn (array $row) => $row['status'] === 'overdue');

        $overdueTotal = round(($overdueInstSum / 100) + $overdueOne->sum('total_amount') + $overdueContract->sum('amount'), 2);

        // Contract balances outstanding — point in time (date range does not
        // apply), so this still includes contract money with no date at all.
        $outstanding = $this->outstandingContracts();

        return [
            'total_due' => $totalDue,
            'count_due' => $countDue,
            'due_expenses' => $dueExpenses,
            'due_contracts' => $dueContracts,
            'overdue_contracts' => round($overdueContract->sum('amount'), 2),
            'paid_expenses' => $paidExpenses,
            'paid_subcontractors' => $paidSubcontractors,
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
                'subcontractor:id,name',
            ])
            ->committed()
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->tap(fn ($q) => $this->applyClientScope($q))
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
     * Per-subcontractor roll-up (point-in-time, all-time figures).
     * Includes EVERY non-cancelled contract — even fully paid ones — so the
     * "Paid to Date" total reflects everything paid to subcontractors, not just
     * contracts that still carry a balance.
     */
    public function subcontractorSummary(): Collection
    {
        return Contract::query()
            ->with(['subcontractor:id,name'])
            ->committed()
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->tap(fn ($q) => $this->applyClientScope($q))
            ->get()
            ->groupBy('subcontractor_id')
            ->map(function (Collection $contracts) {
                $contractValue = round($contracts->sum(fn (Contract $c) => $c->getAdjustedAmount()), 2);
                $paid = round($contracts->sum(fn (Contract $c) => $c->getAmountPaid()), 2);

                return [
                    'subcontractor' => $contracts->first()->subcontractor?->company_name,
                    'contracts_count' => $contracts->count(),
                    'contract_value' => $contractValue,
                    'paid' => $paid,
                    'outstanding' => round($contractValue - $paid, 2),
                ];
            })
            ->sortByDesc('outstanding')
            ->values();
    }

    /**
     * Forward-looking 12-month projection, starting the month AFTER the period —
     * open expense payments and open contract money, by their due dates.
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

            $contractRows = $this->openContractItems()
                ->filter(fn (array $row) => $row['due_date']
                    && $row['due_date']->betweenIncluded($monthStart, $monthEnd));

            $months[] = [
                'month' => $monthStart->translatedFormat('M Y'),
                'count' => $installmentCount + $oneTimeRows->count() + $contractRows->count(),
                'amount' => round(($installmentSum / 100) + $oneTimeRows->sum('total_amount') + $contractRows->sum('amount'), 2),
            ];
        }

        return $months;
    }
}
