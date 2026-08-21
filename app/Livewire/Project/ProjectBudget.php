<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Budget;
use App\Models\Project;
use App\Services\CostCodeLedger;
use Livewire\Component;

class ProjectBudget extends Component
{
    use AuthorizesAbility;

    public Project $project;

    public function mount(Project $project): void
    {
        $this->authorizeAbility('budget.view', $project);

        $this->project = $project;
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        $budgets = Budget::where('project_id', $this->project->id)
            ->with(['sourceTemplate'])
            ->withCount('items')
            ->get();

        $totals = [];
        $rollup = null;

        foreach ($budgets as $budget) {
            $totals[$budget->id] = CostCodeLedger::for($budget)->totals();
            $rollup = CostCodeLedger::addTotals($rollup, $totals[$budget->id]);
        }

        return view('livewire.project.project-budget', [
            'jobSites' => $jobSites,
            'projectBudget' => $budgets->firstWhere('job_site_id', null),
            'jobSiteBudgets' => $budgets->whereNotNull('job_site_id'),
            'budgetTotals' => $totals,
            'rollup' => $rollup,
        ])->layout('components.layouts.app');
    }
}
