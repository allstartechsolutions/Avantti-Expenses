<?php

namespace App\Livewire\Budget;

use App\Models\Budget;
use App\Models\BudgetItem;
use Livewire\Component;

class BudgetShow extends Component
{
    public Budget $budget;

    // Form state for adding/editing budget items
    public $showForm = false;
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
        $this->showForm = true;

        // Set default sort order
        $query = BudgetItem::where('budget_id', $this->budget->id);
        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }
        $this->sort_order = $query->max('sort_order') + 1;
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
        $this->showForm = true;
    }

    public function save()
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

        $this->closeForm();
        $this->refreshBudget();
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
            BudgetItem::where('budget_id', $this->budget->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
            $item->update(['is_default' => true]);
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
        $this->showForm = false;
        $this->resetForm();
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
        return view('livewire.budget.budget-show')
            ->layout('components.layouts.app');
    }
}
