<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\ChangeOrder;
use App\Models\ChangeOrderItem;
use App\Models\Contract;
use App\Models\ContractPaymentItem;
use App\Models\ExpenseItem;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything that has happened to a budget's cost codes, in one pass.
 *
 * Per cost code it answers the six questions a site manager actually asks:
 *
 *   Original   what we budgeted
 *   Changes    what approved change orders did to that budget (signed)
 *   Revised    original + changes — the budget in force today
 *   Committed  money promised but not yet spent: subcontracts, pending POs
 *   Actual     money spent: expenses recorded, subcontract payments made
 *   Remaining  revised − projected cost
 *
 * "Projected" is where the double counting is avoided. A subcontract's paid
 * portion is already inside its scheduled value, and an approved purchase order
 * has already become an expense, so projected is:
 *
 *   contracts scheduled + pending purchase orders + expenses
 *
 * Amounts are handled in cents throughout and converted to dollars on the way
 * out, so nothing drifts by a cent across a long budget.
 */
class CostCodeLedger
{
    /** Aggregates in cents, keyed by budget item id (0 = unassigned). */
    private ?array $buckets = null;

    private ?array $itemIds = null;

    /** false until resolved; null once resolved to "this budget has no default". */
    private BudgetItem|false|null $defaultItem = false;

    public function __construct(public readonly Budget $budget)
    {
    }

    public static function for(Budget $budget): self
    {
        return new self($budget);
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Every budget on a project, each with its own grid, plus a project-wide
     * roll-up. The locations are disjoint, so the roll-up is a straight sum.
     *
     * @return array{budgets: array<int, array{budget: Budget, grid: array}>, totals: ?array}
     */
    public static function forProject(Project $project): array
    {
        $budgets = Budget::where('project_id', $project->id)
            ->with(['jobSite'])
            ->orderByRaw('job_site_id is not null')
            ->get();

        $out = [];
        $rollup = null;

        foreach ($budgets as $budget) {
            $ledger = self::for($budget);
            $grid = $ledger->grid();
            $out[] = ['budget' => $budget, 'grid' => $grid];
            $rollup = self::addTotals($rollup, $grid['totals']);
        }

        return ['budgets' => $out, 'totals' => $rollup];
    }

    /**
     * Add one totals row into another, recomputing the derived figures. Used to
     * roll several budgets into one set of numbers.
     */
    public static function addTotals(?array $carry, array $row): array
    {
        if ($carry === null) {
            return $row;
        }

        foreach ([
            'original', 'changes', 'revised',
            'committed_contracts', 'committed_pos', 'committed',
            'actual_expenses', 'actual_payments', 'actual',
            'projected', 'remaining',
        ] as $key) {
            $carry[$key] = round($carry[$key] + $row[$key], 2);
        }

        $carry['percent_spent'] = $carry['revised'] != 0.0
            ? round($carry['actual'] / $carry['revised'] * 100, 2)
            : null;
        $carry['percent_committed'] = $carry['revised'] != 0.0
            ? round($carry['projected'] / $carry['revised'] * 100, 2)
            : null;
        $carry['over_budget'] = $carry['revised'] > 0 && $carry['projected'] > $carry['revised'];

        return $carry;
    }

    /**
     * The ledger for one cost code. Accepts a model or an id; pass null for the
     * unassigned bucket.
     */
    public function forItem(BudgetItem|int|null $item): array
    {
        if (is_int($item)) {
            $item = $this->budget->items()->find($item);
        }

        return $this->row($item);
    }

    /**
     * Every cost code as a flat list, parents followed by their children, with
     * the unassigned bucket last when anything landed in it.
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->budget->parentItems()->with('children')->get() as $parent) {
            $rows[] = $this->row($parent);

            foreach ($parent->children as $child) {
                $rows[] = $this->row($child);
            }
        }

        if ($unassigned = $this->unassignedRow()) {
            $rows[] = $unassigned;
        }

        return $rows;
    }

    /**
     * Every cost code's ledger keyed by budget item id, for screens that
     * already have the items and just need the figures beside them. Key 0 is
     * the unassigned bucket when anything landed there.
     */
    public function rowsByItem(): array
    {
        $rows = [];

        foreach ($this->rows() as $row) {
            $rows[$row['budget_item_id'] ?? 0] = $row;
        }

        return $rows;
    }

    /**
     * The same figures grouped for display: one section per parent code, with
     * its children, a subtotal and its share of the revised budget.
     *
     * Returns ['sections' => [...], 'unassigned' => ?row, 'totals' => row].
     */
    public function grid(): array
    {
        $sections = [];
        $allRows = [];

        foreach ($this->budget->parentItems()->with('children')->get() as $parent) {
            $rows = [$this->row($parent)];

            foreach ($parent->children as $child) {
                $rows[] = $this->row($child);
            }

            $sections[] = ['item' => $parent, 'rows' => $rows, 'subtotal' => $this->sumRows($rows)];
            $allRows = array_merge($allRows, $rows);
        }

        $unassigned = $this->unassignedRow();

        if ($unassigned) {
            $allRows[] = $unassigned;
        }

        $totals = $this->sumRows($allRows);

        foreach ($sections as &$section) {
            $section['pct_of_budget'] = $totals['revised'] != 0.0
                ? round($section['subtotal']['revised'] / $totals['revised'] * 100, 2)
                : null;
        }

        return ['sections' => $sections, 'unassigned' => $unassigned, 'totals' => $totals];
    }

    /**
     * The same grid with the cost codes nothing has happened to left out — no
     * budget, no approved change, nothing committed and nothing spent. A
     * parent code is kept whenever any of its lines survive, so a section is
     * never left headerless, and the totals row is untouched: the foot of the
     * grid still adds up to the whole budget, listed or not.
     *
     * Adds 'hidden_count' — how many rows were dropped — so the screen can say
     * what it is not showing.
     */
    public static function withActivityOnly(array $grid): array
    {
        $sections = [];
        $hidden = 0;

        foreach ($grid['sections'] as $section) {
            $parent = $section['rows'][0];
            $children = array_slice($section['rows'], 1);
            $kept = array_values(array_filter($children, fn ($row) => self::rowHasActivity($row)));
            $hidden += count($children) - count($kept);

            if (! $kept && ! self::rowHasActivity($parent)) {
                $hidden++;

                continue;
            }

            $section['rows'] = array_merge([$parent], $kept);
            $sections[] = $section;
        }

        $grid['sections'] = $sections;
        $grid['hidden_count'] = $hidden;

        return $grid;
    }

    /**
     * A cost code is worth listing once money has touched it in any way —
     * budgeted, moved by a change order, committed or spent. Note that a code
     * budgeted and then changed back to nothing still counts: the row tells a
     * story even though its revised figure is zero.
     */
    public static function rowHasActivity(array $row): bool
    {
        return (float) $row['original'] != 0.0
            || (float) $row['changes'] != 0.0
            || (float) $row['committed'] != 0.0
            || (float) $row['actual'] != 0.0;
    }

    /**
     * Budget-wide totals.
     */
    public function totals(): array
    {
        return $this->sumRows($this->rows());
    }

    /**
     * Every record behind one cost code's figures, for the drill-down.
     *
     * The bucket rules are the same ones the totals use: a record with no cost
     * code — or one belonging to another budget — belongs to this budget's
     * default item, so the default code's drill-down lists them too.
     *
     * Returns change_orders, pending_change_orders, contracts, purchase_orders,
     * expenses and payments.
     */
    public function transactionsFor(?BudgetItem $item): array
    {
        $key = $item?->id ?? 0;

        return [
            'change_orders' => $this->changeOrderLines($key, [ChangeOrder::STATUS_APPROVED]),
            'pending_change_orders' => $this->changeOrderLines($key, [
                ChangeOrder::STATUS_DRAFT,
                ChangeOrder::STATUS_PENDING,
            ]),
            'contracts' => $this->contractRows($key),
            'purchase_orders' => $this->purchaseOrderRows($key),
            'expenses' => $this->expenseLines($key),
            'payments' => $this->paymentLines($key),
        ];
    }

    /**
     * Narrow a query to the records that land in one bucket. The default item
     * also collects everything uncoded, so its filter is the widest.
     */
    private function scopeToBucket($query, string $column, int $key): void
    {
        $isDefaultBucket = $key === ($this->resolvedDefaultItem()?->id ?? 0);

        if (! $isDefaultBucket) {
            $query->where($column, $key);

            return;
        }

        $ids = $this->itemIds();

        $query->where(function ($q) use ($column, $key, $ids) {
            $q->whereNull($column);

            if ($key !== 0) {
                $q->orWhere($column, $key);
            }

            if ($ids) {
                $q->orWhereNotIn($column, $ids);
            }
        });
    }

    private function changeOrderLines(int $key, array $statuses): Collection
    {
        $query = ChangeOrderItem::with(['changeOrder.jobSite', 'budgetItem'])
            ->whereHas('changeOrder', function ($q) use ($statuses) {
                $q->where('project_id', $this->budget->project_id)
                    ->whereIn('status', $statuses)
                    ->when(
                        is_null($this->budget->job_site_id),
                        fn ($q) => $q->whereNull('job_site_id'),
                        fn ($q) => $q->where('job_site_id', $this->budget->job_site_id)
                    );
            });

        $this->scopeToBucket($query, 'budget_item_id', $key);

        return $query->get()->sortByDesc(fn ($line) => $line->changeOrder->requested_date)->values();
    }

    private function expenseLines(int $key): Collection
    {
        $query = ExpenseItem::with(['expense.supplier', 'expense.jobSite', 'budgetItem'])
            ->whereHas('expense', function ($q) {
                $q->where('project_id', $this->budget->project_id)
                    ->when(
                        is_null($this->budget->job_site_id),
                        fn ($q) => $q->whereNull('job_site_id'),
                        fn ($q) => $q->where('job_site_id', $this->budget->job_site_id)
                    );
            });

        $this->scopeToBucket($query, 'budget_item_id', $key);

        return $query->get()->sortByDesc(fn ($line) => $line->expense->expense_date)->values();
    }

    private function paymentLines(int $key): Collection
    {
        $query = ContractPaymentItem::with(['payment.contract.subcontractor', 'budgetItem'])
            ->whereHas('payment.contract', function ($q) {
                $q->where('project_id', $this->budget->project_id)
                    ->where('job_site_id', $this->budget->job_site_id)
                    ->committed();
            });

        $this->scopeToBucket($query, 'budget_item_id', $key);

        return $query->get()->sortByDesc(fn ($line) => $line->payment->payment_date)->values();
    }

    /**
     * The contracts carrying money on this cost code, with what each has
     * scheduled and paid against it — read through the same schedule the
     * totals use, so the default-code fallback matches.
     */
    private function contractRows(int $key): Collection
    {
        $rows = collect();

        $contracts = Contract::with('subcontractor')
            ->where('project_id', $this->budget->project_id)
            ->where('job_site_id', $this->budget->job_site_id)
            ->committed()
            ->get();

        foreach ($contracts as $contract) {
            foreach ($contract->costCodeSchedule() as $scheduleRow) {
                if ($this->bucketKey($scheduleRow['budget_item_id']) !== $key) {
                    continue;
                }

                $rows->push([
                    'contract' => $contract,
                    'scheduled' => $scheduleRow['scheduled'],
                    'paid' => $scheduleRow['paid'],
                    'percent_complete' => $scheduleRow['percent_complete'],
                    'balance' => round($scheduleRow['scheduled'] - $scheduleRow['paid'], 2),
                ]);
            }
        }

        return $rows->sortByDesc('scheduled')->values();
    }

    /**
     * Purchase orders awaiting approval that carry money on this cost code,
     * with the header's freight, tax and discount apportioned the same way the
     * totals apportion them.
     */
    private function purchaseOrderRows(int $key): Collection
    {
        $rows = collect();

        $orders = PurchaseOrder::with(['supplier', 'items'])
            ->where('project_id', $this->budget->project_id)
            ->when(
                is_null($this->budget->job_site_id),
                fn ($q) => $q->whereNull('job_site_id'),
                fn ($q) => $q->where('job_site_id', $this->budget->job_site_id)
            )
            ->where(function ($q) {
                $q->where('status', 'pending')
                    ->orWhere(fn ($q) => $q->where('status', 'approved')->whereNull('expense_id'));
            })
            ->get();

        foreach ($orders as $order) {
            $weights = [];

            foreach ($order->items as $item) {
                $bucket = $this->bucketKey($item->budget_item_id);
                $weights[$bucket] = ($weights[$bucket] ?? 0) + (int) $item->getRawOriginal('total_amount');
            }

            $orderTotal = (int) $order->getRawOriginal('total_amount');
            $shares = $weights ? self::apportion($orderTotal, $weights) : [$this->bucketKey(null) => $orderTotal];

            if (! isset($shares[$key]) || $shares[$key] === 0) {
                continue;
            }

            $rows->push([
                'order' => $order,
                'amount' => self::toDollars($shares[$key]),
                'is_whole_order' => count($shares) === 1,
            ]);
        }

        return $rows->sortByDesc('amount')->values();
    }

    // =========================================================================
    // ROW BUILDING
    // =========================================================================

    private function row(?BudgetItem $item): array
    {
        $key = $item?->id ?? 0;
        $b = $this->buckets()[$key] ?? $this->emptyBucket();

        $original = $item ? (int) $item->getRawOriginal('budgeted_amount') : 0;
        $revised = $original + $b['changes'];
        $committed = $b['contracts_scheduled'] + $b['pos_pending'];
        $actual = $b['expenses'] + $b['contracts_paid'];
        $projected = $b['contracts_scheduled'] + $b['pos_pending'] + $b['expenses'];

        return [
            'budget_item_id' => $item?->id,
            'code' => $item?->code ?? '',
            'name' => $item?->name ?? __('Unassigned'),
            'is_default' => (bool) ($item?->is_default ?? false),
            'is_parent' => $item ? is_null($item->parent_id) : false,
            'original' => self::toDollars($original),
            'changes' => self::toDollars($b['changes']),
            'revised' => self::toDollars($revised),
            'committed_contracts' => self::toDollars($b['contracts_scheduled']),
            'committed_pos' => self::toDollars($b['pos_pending']),
            'committed' => self::toDollars($committed),
            'actual_expenses' => self::toDollars($b['expenses']),
            'actual_payments' => self::toDollars($b['contracts_paid']),
            'actual' => self::toDollars($actual),
            'projected' => self::toDollars($projected),
            'remaining' => self::toDollars($revised - $projected),
            'percent_spent' => self::percent($actual, $revised),
            'percent_committed' => self::percent($projected, $revised),
            'over_budget' => $revised > 0 && $projected > $revised,
        ];
    }

    /**
     * The unassigned bucket only exists when something actually landed in it —
     * an empty "Unassigned" row is noise, not information.
     */
    private function unassignedRow(): ?array
    {
        $b = $this->buckets()[0] ?? null;

        if (! $b || ! array_filter($b)) {
            return null;
        }

        return $this->row(null);
    }

    private function sumRows(array $rows): array
    {
        $sum = fn (string $key) => round(array_sum(array_column($rows, $key)), 2);

        $revised = $sum('revised');
        $actual = $sum('actual');
        $projected = $sum('projected');

        return [
            'budget_item_id' => null,
            'code' => '',
            'name' => __('Total'),
            'is_default' => false,
            'is_parent' => false,
            'original' => $sum('original'),
            'changes' => $sum('changes'),
            'revised' => $revised,
            'committed_contracts' => $sum('committed_contracts'),
            'committed_pos' => $sum('committed_pos'),
            'committed' => $sum('committed'),
            'actual_expenses' => $sum('actual_expenses'),
            'actual_payments' => $sum('actual_payments'),
            'actual' => $actual,
            'projected' => $projected,
            'remaining' => $sum('remaining'),
            'percent_spent' => self::percent((int) round($actual * 100), (int) round($revised * 100)),
            'percent_committed' => self::percent((int) round($projected * 100), (int) round($revised * 100)),
            'over_budget' => $revised > 0 && $projected > $revised,
        ];
    }

    // =========================================================================
    // AGGREGATION
    // =========================================================================

    /**
     * Every source aggregated once, in cents, keyed by budget item id.
     */
    private function buckets(): array
    {
        if ($this->buckets !== null) {
            return $this->buckets;
        }

        $this->buckets = [];

        $this->collectChangeOrders();
        $this->collectExpenses();
        $this->collectPendingPurchaseOrders();
        $this->collectContracts();

        return $this->buckets;
    }

    /**
     * Approved change orders only. A change order still in draft, pending or
     * rejected does not move the budget — it stays visible on its own screens.
     */
    private function collectChangeOrders(): void
    {
        $rows = DB::table('change_order_items')
            ->join('change_orders', 'change_orders.id', '=', 'change_order_items.change_order_id')
            ->where('change_orders.project_id', $this->budget->project_id)
            ->where('change_orders.status', ChangeOrder::STATUS_APPROVED)
            ->when(
                is_null($this->budget->job_site_id),
                fn ($q) => $q->whereNull('change_orders.job_site_id'),
                fn ($q) => $q->where('change_orders.job_site_id', $this->budget->job_site_id)
            )
            ->groupBy('change_order_items.budget_item_id')
            ->selectRaw('change_order_items.budget_item_id, SUM(change_order_items.amount) as total')
            ->get();

        foreach ($rows as $row) {
            $this->add($row->budget_item_id, 'changes', (int) $row->total);
        }
    }

    /**
     * Every expense line for this location, paid or not: an expense is a cost
     * the moment it is recorded, not the moment it is settled.
     */
    private function collectExpenses(): void
    {
        $rows = DB::table('expense_items')
            ->join('expenses', 'expenses.id', '=', 'expense_items.expense_id')
            ->where('expenses.project_id', $this->budget->project_id)
            ->when(
                is_null($this->budget->job_site_id),
                fn ($q) => $q->whereNull('expenses.job_site_id'),
                fn ($q) => $q->where('expenses.job_site_id', $this->budget->job_site_id)
            )
            ->groupBy('expense_items.budget_item_id')
            ->selectRaw('expense_items.budget_item_id, SUM(expense_items.total_amount) as total')
            ->get();

        foreach ($rows as $row) {
            $this->add($row->budget_item_id, 'expenses', (int) $row->total);
        }
    }

    /**
     * Purchase orders awaiting approval are money the company has lined up but
     * not yet spent. An approved order is deliberately left out: approving it
     * creates the expense, and that expense is already counted above.
     */
    private function collectPendingPurchaseOrders(): void
    {
        $orders = PurchaseOrder::where('project_id', $this->budget->project_id)
            ->when(
                is_null($this->budget->job_site_id),
                fn ($q) => $q->whereNull('job_site_id'),
                fn ($q) => $q->where('job_site_id', $this->budget->job_site_id)
            )
            ->where(function ($q) {
                $q->where('status', 'pending')
                    // Defensive: an approved order that never produced its expense
                    // is still an open commitment rather than a silent gap.
                    ->orWhere(fn ($q) => $q->where('status', 'approved')->whereNull('expense_id'));
            })
            ->with('items:id,purchase_order_id,budget_item_id,total_amount')
            ->get();

        foreach ($orders as $order) {
            $weights = [];

            foreach ($order->items as $item) {
                $key = $this->bucketKey($item->budget_item_id);
                $weights[$key] = ($weights[$key] ?? 0) + (int) $item->getRawOriginal('total_amount');
            }

            $orderTotal = (int) $order->getRawOriginal('total_amount');

            if (! $weights) {
                $this->add(null, 'pos_pending', $orderTotal);

                continue;
            }

            // The order's freight, tax and discount live on the header, not on
            // its lines, so the lines are scaled up to the order total — the
            // same treatment approval gives them when it writes the expense.
            foreach (self::apportion($orderTotal, $weights) as $key => $amount) {
                $this->addToKey($key, 'pos_pending', $amount);
            }
        }
    }

    /**
     * Subcontracts, through the schedule each contract already knows how to
     * build, so the default-code fallback and the contract's own change orders
     * are handled in one place.
     */
    private function collectContracts(): void
    {
        $contracts = Contract::where('project_id', $this->budget->project_id)
            ->where('job_site_id', $this->budget->job_site_id)
            ->committed()
            ->get();

        foreach ($contracts as $contract) {
            foreach ($contract->costCodeSchedule() as $row) {
                $key = $this->bucketKey($row['budget_item_id']);
                $this->addToKey($key, 'contracts_scheduled', (int) round($row['scheduled'] * 100));
                $this->addToKey($key, 'contracts_paid', (int) round($row['paid'] * 100));
            }
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Anything with no cost code — or with one belonging to another budget —
     * falls into this budget's default bucket, and into "Unassigned" only when
     * no default has been set.
     */
    private function bucketKey(?int $budgetItemId): int
    {
        if ($budgetItemId && in_array($budgetItemId, $this->itemIds(), true)) {
            return $budgetItemId;
        }

        return $this->resolvedDefaultItem()?->id ?? 0;
    }

    /** The budget's catch-all item, looked up once. */
    private function resolvedDefaultItem(): ?BudgetItem
    {
        if ($this->defaultItem === false) {
            $this->defaultItem = $this->budget->defaultItem();
        }

        return $this->defaultItem;
    }

    private function itemIds(): array
    {
        return $this->itemIds ??= $this->budget->items()->pluck('id')->all();
    }

    private function add(?int $budgetItemId, string $field, int $cents): void
    {
        $this->addToKey($this->bucketKey($budgetItemId), $field, $cents);
    }

    private function addToKey(int $key, string $field, int $cents): void
    {
        $this->buckets[$key] ??= $this->emptyBucket();
        $this->buckets[$key][$field] += $cents;
    }

    private function emptyBucket(): array
    {
        return [
            'changes' => 0,
            'expenses' => 0,
            'pos_pending' => 0,
            'contracts_scheduled' => 0,
            'contracts_paid' => 0,
        ];
    }

    /**
     * Split an amount across weighted buckets so the parts add up to the whole
     * exactly — largest remainder, the same method the purchase order discount
     * uses, so no bucket is ever a cent out.
     *
     * @param  array<int,int>  $weights
     * @return array<int,int>
     */
    private static function apportion(int $amount, array $weights): array
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            return $weights;
        }

        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $key => $weight) {
            $exact = $amount * $weight / $total;
            $shares[$key] = (int) floor($exact);
            $remainders[$key] = $exact - floor($exact);
            $allocated += $shares[$key];
        }

        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, $amount - $allocated, true) as $key) {
            $shares[$key]++;
        }

        return $shares;
    }

    private static function toDollars(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Null rather than zero when there is no revised budget to measure against:
     * "0% of nothing" reads as under budget when it means nothing at all.
     */
    private static function percent(int $part, int $whole): ?float
    {
        if ($whole == 0) {
            return null;
        }

        return round($part / $whole * 100, 2);
    }
}
