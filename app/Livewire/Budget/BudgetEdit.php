<?php

namespace App\Livewire\Budget;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\CostCodeTemplate;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class BudgetEdit extends Component
{
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

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    protected $validationAttributes = [
        'name' => 'budget name',
        'notes' => 'notes',
    ];

    public function mount(Budget $budget)
    {
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
        $this->validate();

        $this->budget->update([
            'name' => $this->name,
            'notes' => $this->notes ?: null,
        ]);

        session()->flash('message', 'Budget updated successfully.');
        return redirect()->route('budgets.show', $this->budget->id);
    }

    // Import from template methods
    public function openImportModal()
    {
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
        $this->validate([
            'importTemplateId' => 'required|exists:cost_code_templates,id',
        ], [
            'importTemplateId.required' => 'Please select a template.',
        ]);

        $template = CostCodeTemplate::with(['parentCostCodes.children'])->find($this->importTemplateId);

        if (!$template) {
            session()->flash('error', 'Template not found.');
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

        session()->flash('message', 'Cost codes imported from template successfully.');
    }

    // Delete budget
    public function confirmDelete()
    {
        $this->showDeleteConfirmation = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteConfirmation = false;
    }

    public function deleteBudget()
    {
        $projectId = $this->budget->project_id;
        $jobSiteId = $this->budget->job_site_id;

        $this->budget->delete();

        session()->flash('message', 'Budget deleted successfully.');

        if ($jobSiteId) {
            return redirect()->route('jobsites.show', $jobSiteId);
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
