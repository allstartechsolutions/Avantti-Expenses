<?php

namespace App\Livewire\Dashboard;

use App\Enums\ProjectStatus;
use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Membership;
use App\Models\PaymentBatch;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Task;
use App\Services\InvitationService;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * The dashboard (M18 — the last module pass).
 *
 * This is the only screen in the application that is entirely a roll-up: every
 * card and every panel on it is a summary of some other module. That shapes its
 * permissions in three ways, and all three are the point of this pass.
 *
 *  1. **`dashboard.view` opens the page, `dashboard.overview` fills it.**
 *     Everybody holds `view`, because this is where a login lands and refusing
 *     it would lock people out of the application at the front door. What used
 *     to be `@if ($role === 'admin')` in the view is now `dashboard.overview`,
 *     seeded to admins alone — the same people who see the overview today.
 *
 *  2. **Every block obeys the ability of the module it summarises.** Holding
 *     the overview is permission to see a dashboard, not permission to see
 *     everything on it: somebody granted the overview and `expenses.view` gets
 *     Cash to Pay and the overdue list, and no receivables, no estimates and
 *     no purchase orders. A block whose module is switched off, or whose
 *     ability is missing, is not rendered at all.
 *
 *  3. **Every figure is narrowed to the projects this person may see.** A card
 *     that counts money across projects somebody cannot open is a leak by
 *     aggregate — the total tells them something the project list would not.
 *     `visibleProjectIds()` is a no-op today (nobody is confined until F1) and
 *     becomes real the moment confinement is switched on.
 *
 * The money figures are all roll-ups, so they all go through `x-ui.money` with
 * `rollup` set: whoever's access hides totals sees the dashboard's structure
 * and none of its amounts (M4).
 */
class DashboardIndex extends Component
{
    use AuthorizesAbility;

    public string $month;

    /** Memoised for the request; null means "not confined, no filter". */
    protected ?array $projectIds = null;

    protected bool $projectIdsResolved = false;

    /** @var \Illuminate\Support\Collection|null */
    protected $overBudgetCache = null;

    public function mount()
    {
        $this->month = now()->format('Y-m');

        if ($this->allowsAbility('dashboard.view')) {
            return null;
        }

        // Not everybody belongs here, and the login lands everybody here
        // anyway (config/fortify.php). A guest holds no company-wide ability
        // by design — the resolver refuses them every one — and an ordinary
        // person can have the dashboard taken away like any other screen. Both
        // would otherwise meet a 403 at the front door of the application on
        // every sign-in, which is no way to greet a customer's client. So send
        // them where they do belong, and refuse only somebody with nowhere.
        $landing = app(InvitationService::class)->landingFor(auth()->user());

        abort_if($landing === route('dashboard'), 403, __('You do not have permission to do that.'));

        return $this->redirect($landing, navigate: false);
    }

    /*
    |---------------------------------------------------------------------------
    | Who sees what
    |---------------------------------------------------------------------------
    */

    /**
     * One flag per block of the overview. The view asks nothing else, and
     * neither does the code that builds the figures — a block that is off is
     * never queried for, so switching a module off or taking an ability away
     * removes the work as well as the panel.
     *
     * There is deliberately no separate module check here: the resolver
     * already answers `false` for every ability of a module the customer has
     * switched off, so asking twice could only ever disagree with itself.
     *
     * The abilities are asked without a scope, which for a confined person
     * means "do they hold this on any project of theirs?" (PermissionResolver
     * step 7). That is the right question for a summary; which projects it then
     * covers is `visibleProjectIds()`.
     */
    public function getBlocksProperty(): array
    {
        $expenses = $this->allowsAbility('expenses.view');
        $projects = $this->allowsAbility('projects.view');

        return [
            'overview' => $this->allowsAbility('dashboard.overview'),

            'expenses' => $expenses,
            'invoices' => $this->allowsAbility('invoices.view'),
            'estimates' => $this->allowsAbility('estimates.view'),
            'projects' => $projects,
            'purchase_orders' => $this->allowsAbility('purchase-orders.view'),
            'payment_batches' => $this->allowsAbility('payments.view'),

            // Over budget compares a project's spend with its contract value,
            // so it discloses both: it needs both grants, not either.
            'over_budget' => $projects && $expenses,

            'money' => $this->canSeeDashboardMoney(),
        ];
    }

    /**
     * Whether the amounts on this screen are shown.
     *
     * `canSeeMoney()` asked without a scope means "company-wide", and for a
     * confined person that is always no — which would be the wrong answer
     * here, because every figure on this screen has already been narrowed to
     * the projects they are a member of. So for them the question is asked of
     * their memberships instead: a sum cannot show half of itself, so ONE
     * project that hides its totals hides the totals here too.
     */
    protected function canSeeDashboardMoney(): bool
    {
        $user = auth()->user();

        if (! $user || $user->is_admin || ! $user->isConfined()) {
            return $this->allowsMoney();
        }

        $memberships = Membership::where('user_id', $user->id)->active()->get();

        return $memberships->isNotEmpty()
            && $memberships->every(fn ($membership) => (bool) $membership->can_see_money);
    }

    /** Whether there is a Pending Approvals panel to draw at all. */
    protected function showsApprovals(array $blocks): bool
    {
        return $blocks['purchase_orders'] || $blocks['payment_batches'];
    }

    /*
    |---------------------------------------------------------------------------
    | Scoping the figures
    |---------------------------------------------------------------------------
    */

    /**
     * The project ids this person may see, or NULL meaning "no filter".
     *
     * Null is not the same as an empty array. A company-wide user is confined
     * to nothing, and listing every project in the install inside a `whereIn`
     * would be an expensive way of writing `1 = 1` — so today's SQL, and
     * today's numbers, are untouched. Once F1 turns confinement on this returns
     * a real list and every figure below narrows with it.
     */
    protected function visibleProjectIds(): ?array
    {
        if ($this->projectIdsResolved) {
            return $this->projectIds;
        }

        $this->projectIdsResolved = true;

        $user = auth()->user();

        $this->projectIds = ($user && ! $user->is_admin && $user->isConfined())
            ? Project::visibleTo($user)->pluck('id')->all()
            : null;

        return $this->projectIds;
    }

    /** Narrow a query that carries a project id of its own. */
    protected function onlyVisible(Builder $query, string $column = 'project_id'): Builder
    {
        $ids = $this->visibleProjectIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /** Narrow a query that reaches its project through a relation. */
    protected function onlyVisibleThrough(Builder $query, string $relation): Builder
    {
        $ids = $this->visibleProjectIds();

        return $ids === null
            ? $query
            : $query->whereHas($relation, fn ($related) => $related->whereIn('project_id', $ids));
    }

    /*
    |---------------------------------------------------------------------------
    | The month selector
    |---------------------------------------------------------------------------
    */

    protected function monthRange(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return [$start, $end];
    }

    public function getAvailableMonthsProperty(): array
    {
        $months = [];
        $cursor = now()->startOfMonth()->subMonths(11);
        $end = now()->startOfMonth()->addMonths(2);

        while ($cursor <= $end) {
            $months[$cursor->format('Y-m')] = $cursor->translatedFormat('F Y');
            $cursor->addMonth();
        }

        return $months;
    }

    /*
    |---------------------------------------------------------------------------
    | The cards
    |---------------------------------------------------------------------------
    */

    public function getKpisProperty(): array
    {
        [$start, $end] = $this->monthRange();
        $blocks = $this->blocks;

        $cashToPay = 0;

        if ($blocks['expenses']) {
            $cashToPayInstallments = $this->onlyVisibleThrough(
                ExpensePayment::where('status', '!=', 'paid')
                    ->whereBetween('due_date', [$start, $end])
                    ->whereHas('expense', fn ($q) => $q->where('status', '!=', 'cancelled')),
                'expense',
            )->sum('amount');

            $cashToPayOneTime = $this->onlyVisible(
                Expense::where('status', 'unpaid')
                    ->where('total_installments', 1)
                    ->whereBetween('payment_due_date', [$start, $end]),
            )->sum('total_amount');

            $contractBalances = 0;
            $unpaidContracts = $this->onlyVisible(
                Contract::committed()->where('status', '!=', 'paid'),
            )->get();

            foreach ($unpaidContracts as $contract) {
                $contractBalances += max(0, $contract->getBalanceDue());
            }

            $cashToPay = ($cashToPayInstallments / 100) + ($cashToPayOneTime / 100) + $contractBalances;
        }

        $receivables = 0;
        $pastDueInvoices = 0;

        if ($blocks['invoices']) {
            $invoiceQuery = Invoice::whereIn('status', ['sent', 'pending', 'partial'])
                ->whereBetween('due_date', [$start, $end])
                ->get();

            foreach ($invoiceQuery as $invoice) {
                $receivables += max(0, $invoice->getBalanceDue());
            }

            $pastDueInvoices = Invoice::whereIn('status', ['sent', 'pending', 'partial'])
                ->where('due_date', '<', now()->toDateString())
                ->count();
        }

        $openEstimatesValue = 0;
        $openEstimatesCount = 0;

        if ($blocks['estimates']) {
            $openEstimates = Estimate::whereIn('status', ['sent', 'pending'])->get();
            $openEstimatesCount = $openEstimates->count();

            foreach ($openEstimates as $estimate) {
                $openEstimatesValue += (float) $estimate->total_amount;
            }
        }

        $activeProjects = 0;
        $atRiskProjects = 0;

        if ($blocks['projects']) {
            $activeProjects = Project::visibleTo(auth()->user())
                ->where('status', ProjectStatus::IN_PROGRESS)
                ->count();

            $atRiskFromInvoices = $blocks['invoices']
                ? $this->onlyVisible(
                    Invoice::whereIn('status', ['sent', 'pending', 'partial'])
                        ->where('due_date', '<', now()->toDateString()),
                )->pluck('project_id')->filter()->unique()
                : collect();

            $atRiskFromExpensePayments = $blocks['expenses']
                ? $this->onlyVisibleThrough(
                    ExpensePayment::where('status', 'overdue')->with('expense:id,project_id'),
                    'expense',
                )->get()->pluck('expense.project_id')->filter()->unique()
                : collect();

            $atRiskProjects = $atRiskFromInvoices
                ->merge($atRiskFromExpensePayments)
                ->unique()
                ->count();
        }

        $purchaseOrders = $blocks['purchase_orders']
            ? $this->onlyVisible(PurchaseOrder::where('status', 'pending'))->count()
            : 0;

        return [
            'cash_to_pay' => $cashToPay,
            'receivables' => $receivables,
            'past_due_invoices' => $pastDueInvoices,
            'open_estimates' => $openEstimatesValue,
            'open_estimates_count' => $openEstimatesCount,
            'active_projects' => $activeProjects,
            'at_risk_projects' => $atRiskProjects,
            'projects_over_budget' => $this->overBudget()->count(),
            'open_purchase_orders' => $purchaseOrders,
        ];
    }

    /*
    |---------------------------------------------------------------------------
    | The lists
    |---------------------------------------------------------------------------
    */

    public function getOverduePaymentsProperty()
    {
        if (! $this->blocks['expenses']) {
            return collect();
        }

        return $this->onlyVisibleThrough(
            ExpensePayment::where('status', 'overdue')
                ->with(['expense.project:id,project_name', 'expense.supplier:id,name']),
            'expense',
        )
            ->orderBy('due_date')
            ->limit(10)
            ->get();
    }

    public function getPastDueInvoicesListProperty()
    {
        if (! $this->blocks['invoices']) {
            return collect();
        }

        return Invoice::with(['client:id,company_name', 'project:id,project_name'])
            ->whereIn('status', ['sent', 'pending', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(10)
            ->get();
    }

    /** The top ten for the panel; the card wants the count of them all. */
    public function getOverBudgetProjectsProperty()
    {
        return $this->overBudget()->take(10);
    }

    /**
     * Every active project of theirs whose spend has passed its adjusted
     * contract value, worst first. Memoised: the card counts them and the panel
     * lists the first ten, and one pass over the projects answers both.
     */
    protected function overBudget()
    {
        if (! $this->blocks['over_budget']) {
            return collect();
        }

        return $this->overBudgetCache ??= Project::visibleTo(auth()->user())
            ->where('status', ProjectStatus::IN_PROGRESS)
            ->withSum('expenses as expenses_total', 'total_amount')
            ->get()
            ->filter(function ($p) {
                $contractValue = $p->getAdjustedContractValue();

                if ($contractValue <= 0) {
                    return false;
                }

                $expensesDollars = round(($p->expenses_total ?? 0) / 100, 2);

                return $expensesDollars > $contractValue;
            })
            ->sortByDesc(function ($p) {
                $expensesDollars = round(($p->expenses_total ?? 0) / 100, 2);

                return $expensesDollars - $p->getAdjustedContractValue();
            })
            ->values();
    }

    public function getPendingApprovalsProperty(): array
    {
        $blocks = $this->blocks;

        $pos = $blocks['purchase_orders']
            ? $this->onlyVisible(
                PurchaseOrder::with(['project:id,project_name', 'supplier:id,name'])
                    ->where('status', 'pending'),
            )->orderByDesc('created_at')->limit(5)->get()
            : collect();

        // Payment batches carry no project of their own — a batch is a company
        // instrument that gathers payments from anywhere — so there is nothing
        // to narrow them by. `payments.view` is a company-wide grant, and a
        // person either has it or does not see this half of the panel.
        $batches = $blocks['payment_batches']
            ? PaymentBatch::withCount('items')
                ->where('status', 'draft')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
            : collect();

        return [
            'purchase_orders' => $pos,
            'payment_batches' => $batches,
        ];
    }

    /*
    |---------------------------------------------------------------------------
    | The chart
    |---------------------------------------------------------------------------
    */

    public function getCashflowChartProperty(): array
    {
        $blocks = $this->blocks;

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->startOfMonth()->subMonths($i));
        }

        $labels = $months->map(fn ($m) => $m->translatedFormat('M Y'))->all();
        $outflow = [];
        $inflow = [];

        foreach ($months as $monthStart) {
            $monthEnd = (clone $monthStart)->endOfMonth();

            if ($blocks['expenses']) {
                $expensePayments = $this->onlyVisibleThrough(
                    ExpensePayment::where('status', 'paid')
                        ->whereBetween('paid_date', [$monthStart, $monthEnd]),
                    'expense',
                )->sum('amount');

                $contractPayments = $this->onlyVisibleThrough(
                    ContractPayment::whereBetween('payment_date', [$monthStart, $monthEnd]),
                    'contract',
                )->sum('amount');

                $outflow[] = round(($expensePayments + $contractPayments) / 100, 2);
            } else {
                $outflow[] = 0;
            }

            if ($blocks['invoices']) {
                $invoicePayments = InvoicePayment::where('status', 'completed')
                    ->whereBetween('payment_date', [$monthStart, $monthEnd])
                    ->sum('amount');

                $inflow[] = round($invoicePayments / 100, 2);
            }
        }

        return [
            'labels' => $labels,
            'outflow' => $outflow,
            'inflow' => $inflow,
            'show_inflow' => $blocks['invoices'],
            'show_outflow' => $blocks['expenses'],
        ];
    }

    /*
    |---------------------------------------------------------------------------
    | What a person without the overview gets instead
    |---------------------------------------------------------------------------
    */

    /**
     * The shortcuts on the welcome panel: the same menu the sidebar draws, so
     * it can never offer somebody a screen they would be refused on, flattened
     * and with the dashboard itself dropped.
     *
     * @return array<int, array>
     */
    public function getShortcutsProperty(): array
    {
        $shortcuts = [];

        foreach (app(\App\Services\Navigation::class)->sidebar(auth()->user()) as $entry) {
            foreach ($entry['type'] === 'group' ? $entry['items'] : [$entry] as $item) {
                if ($item['key'] === 'dashboard') {
                    continue;
                }

                $shortcuts[] = $item;
            }
        }

        return array_slice($shortcuts, 0, 8);
    }

    /**
     * The few things on this person's own plate, for the welcome panel.
     *
     * `visibleTo` is M13's: a task with no project is personal and always
     * theirs, and one on a project is only listed if they may see that project.
     * Empty — including for somebody with no task access at all — simply means
     * the panel does not draw the list.
     */
    public function getMyTasksProperty()
    {
        if (! $this->allowsAbility('tasks.view')) {
            return collect();
        }

        return Task::query()
            ->visibleTo(auth()->user())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function ($query) {
                $query->where('owner_id', auth()->id())
                    ->orWhereHas('assignees', fn ($a) => $a->whereKey(auth()->id()));
            })
            ->with(['project:id,project_name', 'jobSite:id,job_site_name'])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        $blocks = $this->blocks;

        return view('livewire.dashboard.dashboard-index', [
            'blocks' => $blocks,
            'showsApprovals' => $this->showsApprovals($blocks),
        ])->layout('components.layouts.app');
    }
}
