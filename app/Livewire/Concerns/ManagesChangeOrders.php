<?php

namespace App\Livewire\Concerns;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\ChangeOrder;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The change order editor, shared by the project page and the job site page so
 * both levels behave identically.
 *
 * A change order carries two sides. `co_amount` is the revenue: what the client
 * is now billed, and what the contract value rollups have always counted. The
 * cost lines are what the change does to each cost code's budget, and they only
 * reach the budget once the change order is approved. The gap between the two
 * is the margin — shown at all times, never enforced.
 */
trait ManagesChangeOrders
{
    // Modal state
    public $showChangeOrderModal = false;
    public $changeOrderModalMode = 'create'; // create, edit, view
    public $editingChangeOrder = null;

    // Form fields
    public $co_job_site_id = null;
    public $co_number = '';
    public $co_title = '';
    public $co_requested_date = '';
    public $co_status = ChangeOrder::STATUS_PENDING;
    public $co_description = '';
    public $co_amount = '';
    public $co_file = null;

    /**
     * The RFI this change order is being raised from, if it is.
     *
     * Set from `?fromRfi=` when somebody arrives via "Create change order" on
     * an RFI, and consumed once on save — the two records are linked there,
     * with the answer copied across as its justification.
     */
    public $co_fromRfi = null;
    public $existingFilePath = null;

    /** @var array<int, array{budget_item_id: int, code_display: string, description: string, amount: mixed}> */
    public array $coLines = [];

    public $coLineSearch = '';

    // List filters
    public $changeOrderSearch = '';
    public $changeOrderStatusFilter = 'all';

    /** The project every change order on this screen belongs to. */
    abstract protected function changeOrderProjectId(): int;

    /**
     * The job site this screen is pinned to, or false when the user chooses the
     * location on the form (the project page).
     */
    protected function changeOrderPinnedJobSiteId(): int|false
    {
        return false;
    }

    protected function changeOrderLocationIsPinned(): bool
    {
        return $this->changeOrderPinnedJobSiteId() !== false;
    }

    /** The project or job site this screen writes to. */
    protected function changeOrderScope(): JobSite|Project
    {
        $jobSiteId = $this->changeOrderPinnedJobSiteId();

        return $jobSiteId !== false
            ? JobSite::findOrFail($jobSiteId)
            : Project::findOrFail($this->changeOrderProjectId());
    }

    /** A change order of THIS project, or a 404. */
    protected function changeOrderInScope(int $changeOrderId): ChangeOrder
    {
        return ChangeOrder::where('project_id', $this->changeOrderProjectId())
            ->findOrFail($changeOrderId);
    }

    /**
     * §4b question 2: may the person who raised it approve it?
     *
     * No, by default — the same answer M7 gave for requisitions and M8 gave
     * for quotation awards, and the notation itself says the answer should be
     * the same for all three. `change-orders.approve_own` lifts it.
     */
    public function isSelfApprovedChangeOrder(ChangeOrder $changeOrder): bool
    {
        return auth()->id() !== null && $changeOrder->created_by === auth()->id();
    }

    /**
     * Approving is what revises the budget, so it obeys the ceiling and the
     * self-approval rule. Turning down something still pending costs nothing
     * and needs neither.
     */
    protected function authorizeChangeOrderDecision(ChangeOrder $changeOrder, bool $commitsMoney): void
    {
        $this->authorizeAbility('change-orders.approve', $changeOrder);

        if (! $commitsMoney) {
            return;
        }

        if ($this->isSelfApprovedChangeOrder($changeOrder)) {
            $this->authorizeAbility('change-orders.approve_own', $changeOrder);
        }

        $this->authorizeAbilityWithin(
            'change-orders.approve',
            $changeOrder->costImpactInCents(),
            $changeOrder,
        );
    }

    // =========================================================================
    // MODAL
    // =========================================================================

    /**
     * Arriving from an RFI's "Create change order" button.
     *
     * A query parameter rather than a session flash: the reader can land on
     * this page, look at something else and come back, and the intent should
     * either still be in the URL or be gone.
     *
     * Called from each component's own `mount()` rather than through a
     * `mountManagesChangeOrders()` hook — Livewire unpacks the component's
     * mount parameters into trait hooks, so a hook taking none blows up on
     * every screen that uses this trait.
     */
    public function applyChangeOrderQueryIntent(): void
    {
        $fromRfi = (int) request()->query('fromRfi');

        if ($fromRfi > 0 && $this->allowsAbility('change-orders.create', $this->changeOrderScope())) {
            $this->openChangeOrderFromRfi($fromRfi);
        }
    }

    public function openChangeOrderCreateModal(): void
    {
        $this->authorizeAbility('change-orders.create', $this->changeOrderScope());

        $this->resetChangeOrderForm();

        $this->co_requested_date = now()->format('Y-m-d');
        $this->co_number = $this->nextChangeOrderNumber();
        $this->changeOrderModalMode = 'create';
        $this->showChangeOrderModal = true;

        $this->dispatch('open-modal', 'change-order-modal');
    }

    /**
     * Open the form already carrying what the RFI knows.
     *
     * Pre-filled, never created: the guardrail is that every money-touching
     * artifact is confirmed by a person, so this fills the fields in and waits
     * (docs/RFI-Submittals-modules.md, guardrails).
     */
    public function openChangeOrderFromRfi(int $rfiId): void
    {
        $this->authorizeAbility('change-orders.create', $this->changeOrderScope());

        // The id came from a URL: it has to be an RFI on this project, and one
        // this person may read.
        $rfi = \App\Models\Rfi::query()
            ->visibleTo(Auth::user())
            ->where('project_id', $this->changeOrderProjectId())
            ->find($rfiId);

        if (! $rfi) {
            $this->openChangeOrderCreateModal();

            return;
        }

        $this->openChangeOrderCreateModal();

        $this->co_fromRfi = $rfi->id;
        $this->co_job_site_id = $rfi->job_site_id;
        $this->co_title = $rfi->number.' — '.$rfi->subject;
        $this->co_description = $rfi->answer
            ? __('collaboration.help.as_answered_on_rfi', ['number' => $rfi->number])."\n\n".$rfi->answer
            : $rfi->question;

        // Only if they may see it — the estimate is behind `rfis.view_impact`,
        // and pre-filling it here would walk it around that grant.
        if ($rfi->cost_impact_amount !== null
            && $this->allowsAbility('rfis.view_impact', $rfi->jobSite ?? $rfi->project)) {
            $this->co_amount = (string) $rfi->cost_impact_amount;
        }
    }

    public function openChangeOrderEditModal(int $changeOrderId): void
    {
        $this->authorizeAbility('change-orders.edit', $this->changeOrderInScope($changeOrderId));

        $this->fillChangeOrderForm($changeOrderId);

        $this->changeOrderModalMode = 'edit';
        $this->showChangeOrderModal = true;

        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function openChangeOrderViewModal(int $changeOrderId): void
    {
        $this->authorizeAbility('change-orders.view', $this->changeOrderInScope($changeOrderId));

        $this->fillChangeOrderForm($changeOrderId);

        $this->changeOrderModalMode = 'view';
        $this->showChangeOrderModal = true;

        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function closeChangeOrderModal(): void
    {
        $this->showChangeOrderModal = false;
        $this->resetChangeOrderForm();

        $this->dispatch('close-modal', 'change-order-modal');
    }

    private function fillChangeOrderForm(int $changeOrderId): void
    {
        $changeOrder = ChangeOrder::with('items.budgetItem')
            ->where('project_id', $this->changeOrderProjectId())
            ->findOrFail($changeOrderId);

        $this->resetChangeOrderForm();

        $this->editingChangeOrder = $changeOrder->id;
        $this->co_job_site_id = $changeOrder->job_site_id;
        $this->co_number = $changeOrder->co_number ?? '';
        $this->co_title = $changeOrder->title;
        $this->co_requested_date = $changeOrder->requested_date->format('Y-m-d');
        $this->co_status = $changeOrder->status;
        $this->co_description = $changeOrder->description;
        $this->co_amount = $changeOrder->amount;
        $this->existingFilePath = $changeOrder->file_path;

        $this->coLines = $changeOrder->items->map(fn ($item) => [
            'budget_item_id' => $item->budget_item_id,
            'code_display' => $item->cost_code_display,
            'description' => $item->description ?? '',
            'amount' => $item->amount,
        ])->all();
    }

    private function resetChangeOrderForm(): void
    {
        $this->reset([
            'editingChangeOrder',
            'co_job_site_id',
            'co_number',
            'co_title',
            'co_requested_date',
            'co_description',
            'co_amount',
            'co_file',
            'existingFilePath',
            'coLines',
            'coLineSearch',
        ]);

        $this->co_status = ChangeOrder::STATUS_PENDING;
        $this->resetErrorBag();

        if ($this->changeOrderLocationIsPinned()) {
            $this->co_job_site_id = $this->changeOrderPinnedJobSiteId();
        }
    }

    /**
     * The next number in the project's series, so nobody has to remember where
     * they got to. Only the numeric tail of CO-0007 style numbers is read.
     */
    private function nextChangeOrderNumber(): string
    {
        $highest = ChangeOrder::where('project_id', $this->changeOrderProjectId())
            ->whereNotNull('co_number')
            ->pluck('co_number')
            ->map(fn ($number) => (int) preg_replace('/\D/', '', $number))
            ->max();

        return 'CO-' . str_pad((string) (((int) $highest) + 1), 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // COST LINES
    // =========================================================================

    /**
     * The budget the cost lines are picked from: the one for the location the
     * form is currently pointing at.
     */
    public function changeOrderBudget(): ?Budget
    {
        return Budget::where('project_id', $this->changeOrderProjectId())
            ->where('job_site_id', $this->co_job_site_id ?: null)
            ->first();
    }

    /**
     * Changing the location invalidates every code already picked — they belong
     * to the other location's budget.
     */
    public function updatedCoJobSiteId(): void
    {
        $this->coLines = [];
        $this->coLineSearch = '';
    }

    public function addChangeOrderLine($budgetItemId): void
    {
        $budget = $this->changeOrderBudget();
        $item = $budget ? BudgetItem::where('budget_id', $budget->id)->find($budgetItemId) : null;

        if (! $item) {
            return;
        }

        $this->coLineSearch = '';

        foreach ($this->coLines as $line) {
            if ((int) $line['budget_item_id'] === $item->id) {
                return;
            }
        }

        $this->coLines[] = [
            'budget_item_id' => $item->id,
            'code_display' => $item->code . ' - ' . $item->name,
            'description' => '',
            'amount' => '',
        ];
    }

    public function removeChangeOrderLine(int $index): void
    {
        unset($this->coLines[$index]);
        $this->coLines = array_values($this->coLines);
    }

    /**
     * Put whatever is left of the revenue amount on one line, so a single-code
     * change never has to be added up by hand.
     */
    public function takeChangeOrderRemainder(int $index): void
    {
        if (! isset($this->coLines[$index])) {
            return;
        }

        $others = 0.0;
        foreach ($this->coLines as $i => $line) {
            if ($i !== $index) {
                $others += (float) ($line['amount'] ?: 0);
            }
        }

        $this->coLines[$index]['amount'] = round((float) ($this->co_amount ?: 0) - $others, 2);
    }

    /**
     * Split the revenue amount evenly across every line, to the cent.
     */
    public function splitChangeOrderEvenly(): void
    {
        $count = count($this->coLines);

        if ($count === 0) {
            return;
        }

        $totalCents = (int) round((float) ($this->co_amount ?: 0) * 100);
        $share = intdiv($totalCents, $count);
        $leftover = $totalCents - ($share * $count);

        foreach ($this->coLines as $index => $line) {
            $cents = $share + ($index < abs($leftover) ? ($leftover <=> 0) : 0);
            $this->coLines[$index]['amount'] = round($cents / 100, 2);
        }
    }

    /** Every line cleared in one click, for a change order that turns out to cost nothing. */
    public function clearChangeOrderLines(): void
    {
        $this->coLines = [];
    }

    public function changeOrderCostTotal(): float
    {
        return round(collect($this->coLines)->sum(fn ($line) => (float) ($line['amount'] ?: 0)), 2);
    }

    /** Revenue minus cost: what the change is worth to the company. */
    public function changeOrderMargin(): float
    {
        return round((float) ($this->co_amount ?: 0) - $this->changeOrderCostTotal(), 2);
    }

    public function changeOrderMarginPercent(): ?float
    {
        $revenue = (float) ($this->co_amount ?: 0);

        if (abs($revenue) < 0.01) {
            return null;
        }

        return round($this->changeOrderMargin() / $revenue * 100, 2);
    }

    protected function changeOrderLineSearchResults(): Collection
    {
        if (! $this->coLineSearch) {
            return collect();
        }

        $budget = $this->changeOrderBudget();

        if (! $budget) {
            return collect();
        }

        $selectedIds = array_map(fn ($line) => (int) $line['budget_item_id'], $this->coLines);

        return BudgetItem::with('parent')
            ->where('budget_id', $budget->id)
            ->whereNotIn('id', $selectedIds)
            ->where(function ($q) {
                $q->where('code', 'like', '%' . $this->coLineSearch . '%')
                    ->orWhere('name', 'like', '%' . $this->coLineSearch . '%');
            })
            ->orderBy('sort_order')
            ->take(15)
            ->get();
    }

    // =========================================================================
    // SAVE / DELETE
    // =========================================================================

    protected function changeOrderRules(): array
    {
        return [
            'co_job_site_id' => 'nullable|exists:job_sites,id',
            'co_number' => 'nullable|string|max:50',
            'co_title' => 'required|string|max:255',
            'co_requested_date' => 'required|date',
            'co_status' => 'required|in:' . implode(',', ChangeOrder::STATUSES),
            'co_description' => 'nullable|string',
            'co_amount' => 'required|numeric',
            'co_file' => 'nullable|file|max:10240',
            'coLines.*.amount' => 'required|numeric|not_in:0',
            'coLines.*.description' => 'nullable|string|max:255',
        ];
    }

    protected function changeOrderValidationAttributes(): array
    {
        return [
            'co_job_site_id' => __('location'),
            'co_number' => __('number'),
            'co_title' => __('title'),
            'co_requested_date' => __('requested date'),
            'co_status' => __('status'),
            'co_description' => __('description'),
            'co_amount' => __('amount'),
            'co_file' => __('file'),
            'coLines.*.amount' => __('cost line amount'),
            'coLines.*.description' => __('cost line description'),
        ];
    }

    public function saveChangeOrder(): void
    {
        $this->authorizeAbility(
            $this->editingChangeOrder ? 'change-orders.edit' : 'change-orders.create',
            $this->changeOrderScope(),
        );

        $this->validate($this->changeOrderRules(), [], $this->changeOrderValidationAttributes());

        if (! $this->changeOrderLinesValid()) {
            return;
        }

        $filePath = $this->existingFilePath;

        if ($this->co_file) {
            if ($this->existingFilePath) {
                Storage::delete($this->existingFilePath);
            }
            $filePath = $this->co_file->store('change_orders', 'local');
        }

        $jobSiteId = $this->changeOrderLocationIsPinned()
            ? $this->changeOrderPinnedJobSiteId()
            : ($this->co_job_site_id ?: null);

        $data = [
            'project_id' => $this->changeOrderProjectId(),
            'job_site_id' => $jobSiteId,
            'co_number' => $this->co_number ?: null,
            'title' => $this->co_title,
            'requested_date' => $this->co_requested_date,
            'description' => $this->co_description,
            'amount' => $this->co_amount,
            'file_path' => $filePath,
        ];

        // Returned, so what follows can act on the record that was written —
        // it is created inside the closure and would otherwise be out of scope.
        $changeOrder = DB::transaction(function () use ($data) {
            if ($this->changeOrderModalMode === 'edit' && $this->editingChangeOrder) {
                $changeOrder = ChangeOrder::where('project_id', $this->changeOrderProjectId())
                    ->findOrFail($this->editingChangeOrder);
                $changeOrder->update($data);
            } else {
                $data['created_by'] = Auth::id();
                $changeOrder = ChangeOrder::create($data);
            }

            $this->applyChangeOrderStatus($changeOrder);
            $this->syncChangeOrderLines($changeOrder);

            return $changeOrder;
        });

        // Tie it back to the RFI it was raised from, copying the answer that
        // justified it.
        if ($this->co_fromRfi && $this->changeOrderModalMode !== 'edit') {
            $rfi = \App\Models\Rfi::query()
                ->where('project_id', $this->changeOrderProjectId())
                ->find($this->co_fromRfi);

            $rfi?->linkChangeOrder($changeOrder);
            $this->co_fromRfi = null;
        }

        session()->flash('message', $this->changeOrderModalMode === 'edit'
            ? __('Change order updated successfully!')
            : __('Change order created successfully!'));

        $this->closeChangeOrderModal();
        $this->afterChangeOrderSaved();
    }

    /**
     * Move the record to the status the form asked for, stamping the approval
     * audit through the model rather than writing the columns by hand.
     */
    private function applyChangeOrderStatus(ChangeOrder $changeOrder): void
    {
        if ($changeOrder->status === $this->co_status) {
            return;
        }

        match ($this->co_status) {
            ChangeOrder::STATUS_APPROVED => $changeOrder->approve(Auth::user()),
            ChangeOrder::STATUS_REJECTED => $changeOrder->reject(Auth::user()),
            ChangeOrder::STATUS_PENDING => $changeOrder->returnToPending(),
            default => $changeOrder->forceFill([
                'status' => ChangeOrder::STATUS_DRAFT,
                'approved_at' => null,
                'approved_by' => null,
            ])->save(),
        };
    }

    /**
     * Cost lines must sit on codes from this location's budget — a code from
     * another location would quietly credit the wrong job.
     */
    private function changeOrderLinesValid(): bool
    {
        if (empty($this->coLines)) {
            return true;
        }

        $budget = $this->changeOrderBudget();

        $validIds = $budget
            ? BudgetItem::where('budget_id', $budget->id)->pluck('id')->all()
            : [];

        foreach ($this->coLines as $line) {
            if (! in_array((int) $line['budget_item_id'], $validIds, true)) {
                $this->addError('coLines', __('One or more cost codes do not belong to this location\'s budget. Remove them and pick codes again.'));

                return false;
            }
        }

        return true;
    }

    private function syncChangeOrderLines(ChangeOrder $changeOrder): void
    {
        $changeOrder->items()->delete();

        foreach (array_values($this->coLines) as $index => $line) {
            $changeOrder->items()->create([
                'budget_item_id' => $line['budget_item_id'],
                'description' => $line['description'] ?: null,
                'amount' => $line['amount'],
                'sort_order' => $index,
            ]);
        }
    }

    public function approveChangeOrder(int $changeOrderId): void
    {
        $changeOrder = $this->changeOrderInScope($changeOrderId);

        // Approving puts the cost lines into the budget.
        $this->authorizeChangeOrderDecision($changeOrder, commitsMoney: true);

        $changeOrder->approve(Auth::user());

        session()->flash('message', __('Change order approved. Its cost lines now revise the budget.'));
        $this->afterChangeOrderSaved();
    }

    public function rejectChangeOrder(int $changeOrderId): void
    {
        $changeOrder = $this->changeOrderInScope($changeOrderId);

        // Turning down something still pending is an ordinary review decision.
        // Rejecting one already approved pulls its lines back out of a live
        // budget, which is the narrower `unapprove`.
        if ($changeOrder->undoingAffectsBudget()) {
            $this->authorizeAbility('change-orders.unapprove', $changeOrder);
        } else {
            $this->authorizeChangeOrderDecision($changeOrder, commitsMoney: false);
        }

        $changeOrder->reject(Auth::user());

        session()->flash('message', __('Change order rejected. Its cost lines no longer affect the budget.'));
        $this->afterChangeOrderSaved();
    }

    public function returnChangeOrderToPending(int $changeOrderId): void
    {
        $changeOrder = $this->changeOrderInScope($changeOrderId);

        if ($changeOrder->undoingAffectsBudget()) {
            $this->authorizeAbility('change-orders.unapprove', $changeOrder);
        } else {
            $this->authorizeAbility('change-orders.edit', $changeOrder);
        }

        $changeOrder->returnToPending();

        session()->flash('message', __('Change order moved back to pending. Its cost lines no longer affect the budget.'));
        $this->afterChangeOrderSaved();
    }

    public function deleteChangeOrder(int $changeOrderId): void
    {
        $changeOrder = $this->changeOrderInScope($changeOrderId);

        $this->authorizeAbility('change-orders.delete', $changeOrder);

        // §4b question 4. Deleting an approved change order silently takes its
        // cost lines out of every budget they revised, leaving no record that
        // the revision ever happened. So it is refused outright — un-approve
        // it first, which is a visible act needing `change-orders.unapprove`.
        //
        // This is a rule about the RECORD, like a locked budget in M6, so it
        // binds administrators too.
        abort_if(
            $changeOrder->isApproved(),
            403,
            __('This change order is approved and is revising the budget. Undo the approval before deleting it.'),
        );

        if ($changeOrder->file_path) {
            Storage::delete($changeOrder->file_path);
        }

        // Cost lines go with it through the foreign key cascade.
        $changeOrder->delete();

        session()->flash('message', __('Change order deleted successfully!'));
        $this->afterChangeOrderSaved();
    }

    /** Hook for the host component to refresh whatever it shows alongside. */
    protected function afterChangeOrderSaved(): void
    {
        //
    }

    // =========================================================================
    // LIST
    // =========================================================================

    /**
     * The change orders for this screen, filtered by the search box and the
     * status filter.
     */
    protected function changeOrderQuery()
    {
        return ChangeOrder::with(['jobSite', 'createdBy', 'approvedBy', 'items.budgetItem'])
            ->where('project_id', $this->changeOrderProjectId())
            ->when($this->changeOrderLocationIsPinned(), fn ($q) => $q->where('job_site_id', $this->changeOrderPinnedJobSiteId()))
            ->when($this->changeOrderStatusFilter !== 'all', fn ($q) => $q->where('status', $this->changeOrderStatusFilter))
            ->when($this->changeOrderSearch, function ($q) {
                $term = '%' . $this->changeOrderSearch . '%';
                $q->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                        ->orWhere('co_number', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            });
    }

    /**
     * Headline figures for the cards above the list: what the client is billed,
     * what it costs, and what is still waiting on a decision.
     */
    protected function changeOrderSummary(Collection $changeOrders): array
    {
        $approved = $changeOrders->where('status', ChangeOrder::STATUS_APPROVED);
        $awaiting = $changeOrders->whereIn('status', [ChangeOrder::STATUS_DRAFT, ChangeOrder::STATUS_PENDING]);

        $revenue = round($changeOrders->sum('amount'), 2);
        $approvedRevenue = round($approved->sum('amount'), 2);
        $approvedCost = round($approved->sum('cost_impact'), 2);

        return [
            'count' => $changeOrders->count(),
            'revenue' => $revenue,
            'approved_count' => $approved->count(),
            'approved_revenue' => $approvedRevenue,
            'approved_cost' => $approvedCost,
            'approved_margin' => round($approvedRevenue - $approvedCost, 2),
            'approved_margin_percent' => abs($approvedRevenue) >= 0.01
                ? round(($approvedRevenue - $approvedCost) / $approvedRevenue * 100, 2)
                : null,
            'awaiting_count' => $awaiting->count(),
            'awaiting_revenue' => round($awaiting->sum('amount'), 2),
            'awaiting_cost' => round($awaiting->sum('cost_impact'), 2),
            'uncosted_count' => $changeOrders->filter(fn ($co) => $co->items->isEmpty())->count(),
        ];
    }
}
