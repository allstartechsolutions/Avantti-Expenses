<?php

namespace App\Livewire\Project;

use App\Models\Project;
use Livewire\Component;

class ProjectBudget extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        // Get project-level budget (budget with no job_site_id)
        $projectBudget = $this->project->budget;

        // Get job-site level budgets
        $jobSiteBudgets = $this->project->budgets()
            ->whereNotNull('job_site_id')
            ->withCount('items')
            ->get();

        return view('livewire.project.project-budget', [
            'jobSites' => $jobSites,
            'projectBudget' => $projectBudget,
            'jobSiteBudgets' => $jobSiteBudgets,
        ])->layout('components.layouts.app');
    }
}
