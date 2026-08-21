<?php

namespace App\Livewire\Budget;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Services\CostCodeLedger;
use Livewire\Component;

/**
 * Everything behind one cost code's figures: the change orders that revised it,
 * the contracts and purchase orders committed to it, the expenses charged to it
 * and the contract payments made against it.
 *
 * Also serves the budget's catch-all bucket, where uncoded costs land.
 */
class CostCodeDetail extends Component
{
    use AuthorizesAbility;

    public Budget $budget;

    public ?BudgetItem $item = null;

    /**
     * $budgetItem is deliberately untyped: the route also serves the budget's
     * unassigned bucket, which has no item at all, and an implicit binding
     * would 404 on the way in rather than showing that page.
     */
    public function mount(Budget $budget, $budgetItem = null)
    {
        $this->authorizeAbility('budget.view', $budget);

        $this->budget = $budget->load(['project', 'jobSite']);

        if (! $budgetItem) {
            return;
        }

        $item = $budgetItem instanceof BudgetItem
            ? $budgetItem
            : BudgetItem::findOrFail($budgetItem);

        abort_unless($item->budget_id === $budget->id, 404);

        $this->item = $item->load(['parent', 'children']);
    }

    public function getTitleProperty(): string
    {
        if (! $this->item) {
            return __('Unassigned');
        }

        return $this->item->code . ' - ' . $this->item->name;
    }

    public function render()
    {
        $ledger = CostCodeLedger::for($this->budget);
        $rows = $ledger->rowsByItem();

        // A parent code carries its own money as well as its children's, so both
        // are shown: its own row, and a roll-up of the section it heads.
        $children = collect();

        if ($this->item && is_null($this->item->parent_id)) {
            $children = $this->item->children->map(fn ($child) => [
                'item' => $child,
                'row' => $rows[$child->id] ?? null,
            ]);
        }

        return view('livewire.budget.cost-code-detail', [
            'row' => $ledger->forItem($this->item),
            'transactions' => $ledger->transactionsFor($this->item),
            'children' => $children,
        ])->layout('components.layouts.app');
    }
}
