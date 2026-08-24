<?php

namespace App\Livewire\Budget;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Services\CostCodeLedger;
use Livewire\Component;

class BudgetShow extends Component
{
    use AuthorizesAbility;

    public Budget $budget;

    /** The lock / unlock dialog's optional note. */
    public string $lockReason = '';

    /** The add/edit cost code dialog. */
    public const FORM_MODAL = 'budget-item-modal';

    // Form state for adding/editing budget items
    public $editingItemId = null;
    public $parentId = null;

    // Form fields
    public $code = '';
    public $name = '';
    public $description = '';
    public $budgeted_amount = '';
    public $sort_order = 0;

    protected function rules()
    {
        $uniqueRule = 'unique:budget_items,code,';
        $uniqueRule .= $this->editingItemId ? $this->editingItemId : 'NULL';
        $uniqueRule .= ',id,budget_id,' . $this->budget->id;

        return [
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'budgeted_amount' => 'required|numeric|min:0',
            'sort_order' => 'integer|min:0',
        ];
    }


    public function mount(Budget $budget)
    {
        $this->authorizeAbility('budget.view', $budget);

        $this->budget = $budget->load([
            'project', 'jobSite', 'sourceTemplate', 'creator', 'parentItems.children',
            'lockedBy', 'lockHistories.user',
        ]);
    }

    // =========================================================================
    // LOCKING
    // =========================================================================

    /**
     * Every write to the PLAN goes through here first.
     *
     * A locked budget is a frozen baseline, so the refusal is about the record
     * and not about the person: holding `budget.edit` does not make a locked
     * budget editable, and the person who may unlock it has to do that first —
     * deliberately, so that reopening a baseline is a visible act with a line
     * in its history rather than a side effect of typing.
     */
    protected function refuseIfLocked(): void
    {
        abort_if(
            $this->budget->isLocked(),
            403,
            __('This budget is locked. Unlock it before changing the plan.'),
        );
    }

    public function lockBudget(): void
    {
        $this->authorizeAbility('budget.lock', $this->budget);

        $this->budget->lock(auth()->user(), $this->lockReason);

        $this->lockReason = '';
        $this->refreshBudget();

        session()->flash('message', __('Budget locked. Its cost codes and planned amounts are now fixed.'));
    }

    public function unlockBudget(): void
    {
        $this->authorizeAbility('budget.lock', $this->budget);

        $this->budget->unlock(auth()->user(), $this->lockReason);

        $this->lockReason = '';
        $this->refreshBudget();

        session()->flash('message', __('Budget unlocked. The plan can be changed again.'));
    }

    /** A cost code of THIS budget, or a 404. */
    protected function itemInScope($itemId): BudgetItem
    {
        return BudgetItem::where('budget_id', $this->budget->id)->findOrFail($itemId);
    }

    /**
     * Get the back URL based on budget context.
     */
    public function getBackUrlProperty(): string
    {
        if ($this->budget->job_site_id) {
            return route('jobsites.overview', $this->budget->job_site_id);
        }

        return route('projects.budget', $this->budget->project_id);
    }

    /**
     * Get the back label based on budget context.
     */
    public function getBackLabelProperty(): string
    {
        if ($this->budget->job_site_id) {
            return 'Back to Job Site';
        }

        return 'Back to Project';
    }

    public function openAddForm($parentId = null)
    {
        $this->authorizeAbility('budget.create', $this->budget);
        $this->refuseIfLocked();

        $this->resetForm();
        $this->parentId = $parentId;
        $this->sort_order = $this->nextSortOrder($parentId);

        $this->dispatch('open-modal', self::FORM_MODAL);
    }

    /**
     * The next free position under a parent, so the user never has to key one in.
     */
    private function nextSortOrder($parentId): int
    {
        $query = BudgetItem::where('budget_id', $this->budget->id);

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return (int) $query->max('sort_order') + 1;
    }

    public function openEditForm($itemId)
    {
        $item = $this->itemInScope($itemId);

        $this->authorizeAbility('budget.edit', $this->budget);
        $this->refuseIfLocked();

        $this->resetForm();
        $this->editingItemId = $item->id;
        $this->parentId = $item->parent_id;
        $this->code = $item->code;
        $this->name = $item->name;
        $this->description = $item->description ?? '';
        $this->budgeted_amount = $item->budgeted_amount;
        $this->sort_order = $item->sort_order;

        $this->dispatch('open-modal', self::FORM_MODAL);
    }

    /**
     * @param  bool  $addAnother  Keep the dialog open, cleared and ready for the
     *                            next code under the same parent. Adding cost
     *                            codes is done in runs, not one at a time.
     */
    public function save($addAnother = false)
    {
        $this->authorizeAbility(
            $this->editingItemId ? 'budget.edit' : 'budget.create',
            $this->budget,
        );
        $this->refuseIfLocked();

        $this->validate();

        $data = [
            'budget_id' => $this->budget->id,
            'parent_id' => $this->parentId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'budgeted_amount' => $this->budgeted_amount,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editingItemId) {
            $item = $this->itemInScope($this->editingItemId);
            $item->update($data);
            session()->flash('message', __('Budget item updated successfully.'));
        } else {
            BudgetItem::create($data);
            session()->flash('message', __('Budget item added successfully.'));
        }

        $this->refreshBudget();

        if ($addAnother && ! $this->editingItemId) {
            $parentId = $this->parentId;
            $this->resetForm();
            $this->parentId = $parentId;
            $this->sort_order = $this->nextSortOrder($parentId);
            $this->dispatch('cost-code-saved');

            return;
        }

        $this->closeForm();
    }

    /**
     * Mark an item as the default cost code for this budget (uncoded
     * contract/payment amounts roll into it). Clicking the current
     * default clears it.
     */
    public function toggleDefaultItem($itemId)
    {
        $item = $this->itemInScope($itemId);

        $this->authorizeAbility('budget.edit', $this->budget);
        $this->refuseIfLocked();

        if ($item->is_default) {
            $item->update(['is_default' => false]);
            session()->flash('message', __('Default cost code cleared.'));
        } else {
            $this->budget->setDefaultItem($item);
            session()->flash('message', __(':code is now the default cost code.', ['code' => $item->code . ' - ' . $item->name]));
        }

        $this->refreshBudget();
    }

    public function deleteItem($itemId)
    {
        $item = $this->itemInScope($itemId);

        $this->authorizeAbility('budget.delete', $this->budget);
        $this->refuseIfLocked();

        // Check if it has children
        if ($item->children()->count() > 0) {
            session()->flash('error', __('Cannot delete an item that has child items. Delete the children first.'));
            return;
        }

        $item->delete();
        session()->flash('message', __('Budget item deleted successfully.'));
        $this->refreshBudget();
    }

    public function closeForm()
    {
        $this->resetForm();
        $this->dispatch('close-modal', self::FORM_MODAL);
    }

    private function resetForm()
    {
        $this->editingItemId = null;
        $this->parentId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->budgeted_amount = '';
        $this->sort_order = 0;
        $this->resetValidation();
    }

    private function refreshBudget()
    {
        $this->budget = $this->budget->fresh(['project', 'jobSite', 'sourceTemplate', 'creator', 'parentItems.children']);
    }

    public function render()
    {
        $ledger = CostCodeLedger::for($this->budget);

        return view('livewire.budget.budget-show', [
            'ledgerRows' => $ledger->rowsByItem(),
            'ledgerTotals' => $ledger->totals(),
        ])->layout('components.layouts.app');
    }
}
