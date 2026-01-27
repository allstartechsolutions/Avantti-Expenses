<?php

namespace App\Livewire\Project;

use App\Models\Project;
use Livewire\Component;

class ProjectOverview extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project->load(['client', 'createdBy']);
    }

    public function render()
    {
        return view('livewire.project.project-overview')
            ->layout('components.layouts.app');
    }
}
