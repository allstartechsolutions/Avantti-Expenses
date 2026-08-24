<?php

namespace App\Livewire\Budget;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Budget;
use App\Models\CostCodeTemplate;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BudgetCreate extends Component
{
    use AuthorizesAbility;

    public ?Project $project = null;
    public ?JobSite $jobSite = null;

    public $name = '';
    public $notes = '';
    public $use_template = false;
    public $source_template_id = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'notes' => 'nullable|string|max:2000',
        'source_template_id' => 'nullable|exists:cost_code_templates,id',
    ];



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

    public function mount(?Project $project = null, ?JobSite $jobSite = null)
    {
        // If coming from job site route, load the job site and its project
        if ($jobSite && $jobSite->exists) {
            $this->jobSite = $jobSite;
            $this->project = $jobSite->project;
        } elseif ($project && $project->exists) {
            $this->project = $project;
        }

        // Answered against the job site where there is one: a site membership
        // overrides the project's, in both directions.
        $this->authorizeAbility('budget.create', $this->budgetScope());

        // Check if budget already exists for this location
        if ($this->budgetExists()) {
            session()->flash('error', __('A budget already exists for this location.'));
            return redirect($this->getBackUrl());
        }

        // Pre-select default template if exists
        $defaultTemplate = CostCodeTemplate::where('is_default', true)->first();
        if ($defaultTemplate) {
            $this->source_template_id = $defaultTemplate->id;
            $this->use_template = true;
        }
    }

    /**
     * The record this budget is being created against.
     *
     * Neither one bound means the route was reached with nothing to hang the
     * budget on — a 404 rather than a permission question with no subject,
     * which would be answered by role and then fail further down anyway.
     */
    protected function budgetScope(): JobSite|Project
    {
        $scope = $this->jobSite ?? $this->project;

        abort_if($scope === null, 404, 'Project or Job Site required');

        return $scope;
    }

    /**
     * Check if a budget already exists for this location.
     */
    protected function budgetExists(): bool
    {
        if ($this->jobSite) {
            return Budget::where('job_site_id', $this->jobSite->id)->exists();
        }

        return Budget::where('project_id', $this->project->id)
            ->whereNull('job_site_id')
            ->exists();
    }

    /**
     * Get the back URL based on context.
     */
    protected function getBackUrl(): string
    {
        if ($this->jobSite) {
            return route('job-sites.show', $this->jobSite->id);
        }

        return route('projects.budget', $this->project->id);
    }

    /**
     * Get the location name for display.
     */
    public function getLocationNameProperty(): string
    {
        if ($this->jobSite) {
            return $this->jobSite->job_site_name;
        }

        return 'Project (General)';
    }

    public function save()
    {
        $this->authorizeAbility('budget.create', $this->budgetScope());

        $this->validate();

        $budget = Budget::create([
            'project_id' => $this->project->id,
            'job_site_id' => $this->jobSite?->id,
            'name' => $this->name,
            'notes' => $this->notes ?: null,
            'source_template_id' => $this->use_template && $this->source_template_id ? $this->source_template_id : null,
            'created_by' => Auth::id(),
        ]);

        // Apply template if selected
        if ($this->use_template && $this->source_template_id) {
            $template = CostCodeTemplate::find($this->source_template_id);
            if ($template) {
                $budget->applyTemplate($template);
            }
        }

        session()->flash('message', __('Budget created successfully!'));
        return redirect()->route('budgets.show', $budget->id);
    }

    public function render()
    {
        $templates = CostCodeTemplate::orderBy('name')->get();

        return view('livewire.budget.budget-create', [
            'templates' => $templates,
        ])->layout('components.layouts.app');
    }
}
