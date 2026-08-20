<?php

namespace App\Livewire\Budget;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Services\CostCodeLedger;
use Livewire\Component;

class BudgetShow extends Component
{
    public Budget $budget;

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

    protected $validationAttributes = [
        'code' => 'code',
        'name' => 'name',
        'description' => 'description',
        'budgeted_amount' => 'budgeted amount',
        'sort_order' => 'sort order',
    ];

    public function mount(Budget $budget)
    {
        $this->budget = $budget->load(['project', 'jobSite', 'sourceTemplate', 'creator', 'parentItems.children']);
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
        $item = BudgetItem::findOrFail($itemId);

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
            $item = BudgetItem::findOrFail($this->editingItemId);
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
        $item = BudgetItem::where('budget_id', $this->budget->id)->findOrFail($itemId);

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
        $item = BudgetItem::findOrFail($itemId);

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
