<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\ExpensePayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the Expense Report.
 *
 * Where the Accounts Payable report answers "what do we owe and when" (dated by
 * due date, includes subcontractor contracts), this report answers "where did
 * the money go" — it rolls EXPENSES up by project/jobsite, by vendor, and by
 * cost code, with paid / outstanding / overdue columns for each grouping.
 *
 * Scope:
 *   - Expenses only (one-time + installment). Contracts are NOT included.
 *   - The date range filters by expense_date (when the cost was incurred) or,
 *     in due-date basis, by payment due date: one-time expenses match on
 *     COALESCE(payment_due_date, expense_date); installment expenses match
 *     when ANY installment is due in the range. Row amounts are always
 *     whole-expense figures regardless of basis.
 *   - Overdue is DERIVED from due date vs. today, not a stored status.
 *   - Cancelled expenses are always excluded.
 *
 * The filtered expense set is loaded once (with its payments and line items)
 * and every KPI / grouping is computed from that one collection in PHP.
 */
class ExpenseReportService
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
        protected string $categoryFilter = '',
        protected string $clientFilter = '',
        protected string $statusFilter = 'all',
        protected string $dateBasis = 'expense',
    ) {
        $this->start = Carbon::parse($fromDate)->startOfDay();
        $this->end = Carbon::parse($toDate)->endOfDay();
        $this->today = Carbon::now()->startOfDay();
    }

    // =========================================================================
    // BASE DATA — loaded once, normalized, and cached.
    // =========================================================================

    /**
     * Non-cancelled expenses matching the filters, normalized with computed
     * total / paid / outstanding / overdue amounts (all in dollars).
     */
    public function expenses(): Collection
    {
        $from = $this->start->toDateString();
        $to = $this->end->toDateString();

        return $this->cache ??= Expense::query()
            ->where('status', '!=', 'cancelled')
            ->when($this->dateBasis !== 'due', fn ($q) => $q->whereBetween('expense_date', [$from, $to]))
            ->when($this->dateBasis === 'due', function ($q) use ($from, $to) {
                $q->where(function ($q) use ($from, $to) {
                    $q->where(function ($q) use ($from, $to) {
                        $q->where('total_installments', 1)
                            ->whereRaw('COALESCE(payment_due_date, expense_date) BETWEEN ? AND ?', [$from, $to]);
                    })->orWhere(function ($q) use ($from, $to) {
                        $q->where('total_installments', '>', 1)
                            ->whereHas('payments', fn ($p) => $p->whereBetween('due_date', [$from, $to]));
                    });
                });
            })
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->jobSiteFilter, fn ($q) => $q->where('job_site_id', $this->jobSiteFilter))
            ->when($this->vendorFilter, fn ($q) => $q->where('supplier_id', $this->vendorFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('item_type', $this->categoryFilter))
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
            ->with([
                'project:id,project_name',
                'jobSite:id,job_site_name',
                'supplier:id,name',
                'payments',
                'items:id,expense_id,budget_item_id,item_name,total_amount',
                'items.budgetItem:id,code,name',
            ])
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn (Expense $e) => $this->normalize($e))
            ->filter(fn (array $r) => $this->passesStatus($r))
            ->values();
    }

    /**
     * Reduce one expense to the figures every grouping needs. Computed from the
     * already-loaded payments collection to avoid per-row queries.
     */
    protected function normalize(Expense $e): array
    {
        $total = (float) $e->total_amount;

        if ($e->total_installments > 1) {
            // Collection sums go through the dollar accessor — no cents conversion here.
            $paid = round($e->payments->where('status', 'paid')->sum('amount'), 2);
            $overdue = round($e->payments
                ->filter(fn (ExpensePayment $p) => $p->status !== 'paid' && $p->due_date && $p->due_date->lt($this->today))
                ->sum('amount'), 2);
        } else {
            $isPaid = $e->status === 'paid';
            $paid = $isPaid ? $total : 0.0;
            $due = $e->payment_due_date ?? $e->expense_date;
            $overdue = (! $isPaid && $due && $due->lt($this->today)) ? round($total - $paid, 2) : 0.0;
        }

        // Representative due date: one-time = its due date; installments = the
        // earliest installment due in the filtered range (why the row matched),
        // falling back to the earliest installment overall.
        if ($e->total_installments > 1) {
            $dueDates = $e->payments->pluck('due_date')->filter()->sort();
            $dueDate = $dueDates->first(fn (Carbon $d) => $d->between($this->start, $this->end)) ?? $dueDates->first();
        } else {
            $dueDate = $e->payment_due_date ?? $e->expense_date;
        }

        return [
            'expense' => $e,
            'expense_date' => $e->expense_date,
            'due_date' => $dueDate,
            'item' => $e->item_name,
            'project' => $e->project?->project_name,
            'project_id' => $e->project_id,
            'job_site' => $e->jobSite?->job_site_name,
            'job_site_id' => $e->job_site_id,
            'vendor' => $e->supplier?->name,
            'vendor_id' => $e->supplier_id,
            'category' => $e->item_type,
            'payment_label' => $e->total_installments > 1
                ? ($e->payments->where('status', 'paid')->count() . '/' . $e->total_installments)
                : '1x',
            'total' => $total,
            'paid' => $paid,
            'outstanding' => round($total - $paid, 2),
            'overdue' => $overdue,
        ];
    }

    /**
     * Apply the (derived) status filter in PHP, since outstanding/overdue are
     * computed, not stored.
     */
    protected function passesStatus(array $r): bool
    {
        return match ($this->statusFilter) {
            'paid' => $r['outstanding'] <= 0,
            'unpaid' => $r['outstanding'] > 0,
            'overdue' => $r['overdue'] > 0,
            'pending' => $r['outstanding'] > 0 && $r['overdue'] <= 0,
            default => true, // all
        };
    }

    protected function aggregate(Collection $group): array
    {
        return [
            'total' => round($group->sum('total'), 2),
            'paid' => round($group->sum('paid'), 2),
            'outstanding' => round($group->sum('outstanding'), 2),
            'overdue' => round($group->sum('overdue'), 2),
            'count' => $group->count(),
        ];
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    public function kpis(): array
    {
        return $this->aggregate($this->expenses());
    }

    /**
     * Expenses rolled up per project, each with its job sites nested.
     * Project-level expenses (no job site) appear under a null job_site_id.
     */
    public function byProject(): Collection
    {
        return $this->expenses()
            ->groupBy('project_id')
            ->map(function (Collection $group) {
                $jobsites = $group
                    ->groupBy(fn (array $r) => $r['job_site_id'] ?? 0)
                    ->map(fn (Collection $g) => array_merge([
                        'job_site' => $g->first()['job_site'],
                        'job_site_id' => $g->first()['job_site_id'],
                    ], $this->aggregate($g)))
                    ->sortByDesc('total')
                    ->values();

                return array_merge([
                    'project' => $group->first()['project'],
                    'project_id' => $group->first()['project_id'],
                    'jobsites' => $jobsites,
                ], $this->aggregate($group));
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Expenses rolled up per vendor (supplier). Expenses with no supplier are
     * grouped under a null vendor.
     */
    public function byVendor(): Collection
    {
        return $this->expenses()
            ->groupBy('vendor_id')
            ->map(fn (Collection $g) => array_merge([
                'vendor' => $g->first()['vendor'],
                'vendor_id' => $g->first()['vendor_id'],
            ], $this->aggregate($g)))
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Committed cost rolled up per cost code (budget item), at the line-item
     * level. Payments are tracked per expense, not per line, so this view shows
     * total committed cost only (no paid/outstanding split). Line items without
     * a cost code — and expenses with no line items — fall under "Unassigned".
     */
    public function byCostCode(): Collection
    {
        $buckets = [];

        foreach ($this->expenses() as $r) {
            /** @var Expense $expense */
            $expense = $r['expense'];

            if ($expense->items->isNotEmpty()) {
                foreach ($expense->items as $item) {
                    $this->addToCostBucket($buckets, $item->budgetItem, (float) $item->total_amount);
                }
            } else {
                $this->addToCostBucket($buckets, null, $r['total']);
            }
        }

        return collect($buckets)
            ->map(fn (array $b) => [
                'code' => $b['code'],
                'total' => round($b['total'], 2),
                'count' => $b['count'],
            ])
            ->sortByDesc('total')
            ->values();
    }

    protected function addToCostBucket(array &$buckets, $budgetItem, float $amount): void
    {
        $key = $budgetItem?->id ?? 0;
        $label = $budgetItem ? ($budgetItem->code . ' - ' . $budgetItem->name) : __('Unassigned');

        if (! isset($buckets[$key])) {
            $buckets[$key] = ['code' => $label, 'total' => 0.0, 'count' => 0];
        }

        $buckets[$key]['total'] += $amount;
        $buckets[$key]['count']++;
    }

    /**
     * Flat transaction list for the detail tab and the CSV/PDF exports.
     */
    public function detail(): Collection
    {
        // Sort by the date the detail table displays: due date on due basis,
        // expense date otherwise.
        $dateKey = $this->dateBasis === 'due' ? 'due_date' : 'expense_date';

        return $this->expenses()
            ->map(fn (array $r) => collect($r)->except('expense')->all())
            ->sortByDesc(fn (array $r) => $r[$dateKey]?->format('Y-m-d') ?? '')
            ->values();
    }
}
