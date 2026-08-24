<?php

namespace App\Livewire\Budget;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\CostCodeTemplate;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class BudgetEdit extends Component
{
    use AuthorizesAbility;

    public Budget $budget;

    // Budget form fields
    public $name = '';
    public $notes = '';

    // Import template state
    public $showImportModal = false;
    public $importTemplateId = '';
    public $importMode = 'merge';

    // Delete confirmation
    public $showDeleteConfirmation = false;


    /**
     * Only the names that differ from the shared map in
     * lang/<locale>/validation.php — everything else falls through to it.
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('budget name'),
        ];
    }

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ];
    }


    public function mount(Budget $budget)
    {
        $this->authorizeAbility('budget.edit', $budget);

        $this->budget = $budget->load(['project', 'jobSite', 'sourceTemplate']);
        $this->name = $budget->name;
        $this->notes = $budget->notes ?? '';
    }

    /**
     * Get the back URL based on budget context.
     */
    public function getBackUrlProperty(): string
    {
        return route('budgets.show', $this->budget->id);
    }

    public function save()
    {
        $this->authorizeAbility('budget.edit', $this->budget);
        $this->refuseIfLocked();

        $this->validate();

        $this->budget->update([
            'name' => $this->name,
            'notes' => $this->notes ?: null,
        ]);

        session()->flash('message', __('Budget updated successfully.'));
        return redirect()->route('budgets.show', $this->budget->id);
    }

    // Import from template methods
    public function openImportModal()
    {
        $this->authorizeAbility('budget.edit', $this->budget);
        $this->refuseIfLocked();

        $this->importTemplateId = '';
        $this->importMode = 'merge';
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importTemplateId = '';
    }

    public function importTemplate()
    {
        // Importing can REPLACE every cost code, so it is the plan changing in
        // the largest possible way.
        $this->authorizeAbility('budget.edit', $this->budget);
        $this->refuseIfLocked();

        $this->validate([
            'importTemplateId' => 'required|exists:cost_code_templates,id',
        ], [
            'importTemplateId.required' => 'Please select a template.',
        ]);

        $template = CostCodeTemplate::with(['parentCostCodes.children'])->find($this->importTemplateId);

        if (!$template) {
            session()->flash('error', __('Template not found.'));
            return;
        }

        DB::transaction(function () use ($template) {
            // If replace mode, delete existing items
            if ($this->importMode === 'replace') {
                $this->budget->items()->delete();
            }

            // Update source template reference
            $this->budget->update(['source_template_id' => $template->id]);

            $codeToId = [];

            // First pass: create/update all parent items (no parent_id)
            foreach ($template->parentCostCodes as $parentCode) {
                $item = $this->budget->items()->updateOrCreate(
                    ['code' => $parentCode->code],
                    [
                        'name' => $parentCode->name,
                        'description' => $parentCode->description,
                        'parent_id' => null,
                        'budgeted_amount' => 0, // Stored in cents, $0.00
                        'sort_order' => $parentCode->sort_order,
                    ]
                );
                $codeToId[$parentCode->code] = $item->id;
            }

            // Second pass: create/update child items
            foreach ($template->parentCostCodes as $parentCode) {
                foreach ($parentCode->children as $childCode) {
                    $parentId = $codeToId[$parentCode->code] ?? null;

                    if (!$parentId) {
                        continue;
                    }

                    $this->budget->items()->updateOrCreate(
                        ['code' => $childCode->code],
                        [
                            'name' => $childCode->name,
                            'description' => $childCode->description,
                            'parent_id' => $parentId,
                            'budgeted_amount' => 0,
                            'sort_order' => $childCode->sort_order,
                        ]
                    );
                }
            }
        });

        $this->closeImportModal();
        $this->budget = $this->budget->fresh(['project', 'jobSite', 'sourceTemplate']);

        session()->flash('message', __('Cost codes imported from template successfully.'));
    }

    /**
     * A locked budget is a frozen baseline; changing or deleting the plan is
     * refused until somebody holding `budget.lock` reopens it, which leaves a
     * line in the budget's history.
     */
    protected function refuseIfLocked(): void
    {
        abort_if(
            $this->budget->isLocked(),
            403,
            __('This budget is locked. Unlock it before changing the plan.'),
        );
    }

    // Delete budget
    public function confirmDelete()
    {
        $this->authorizeAbility('budget.delete', $this->budget);
        $this->refuseIfLocked();

        $this->showDeleteConfirmation = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteConfirmation = false;
    }

    public function deleteBudget()
    {
        $this->authorizeAbility('budget.delete', $this->budget);
        $this->refuseIfLocked();

        $projectId = $this->budget->project_id;
        $jobSiteId = $this->budget->job_site_id;

        $this->budget->delete();

        session()->flash('message', __('Budget deleted successfully.'));

        if ($jobSiteId) {
            return redirect()->route('jobsites.overview', $jobSiteId);
        }

        return redirect()->route('projects.budget', $projectId);
    }

    public function render()
    {
        $templates = CostCodeTemplate::orderBy('name')->get();

        return view('livewire.budget.budget-edit', [
            'templates' => $templates,
        ])->layout('components.layouts.app');
    }
}
