<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\ManagesTeam;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;

/**
 * Who is on this project, and what each of them may do here.
 *
 * A membership granted at this level also covers every job site under the
 * project, unless that site gives the person something of its own.
 */
class ProjectTeam extends Component
{
    use ManagesTeam;

    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project;

        $this->authorizeAbility('team.view', $project);
    }

    protected function contextScope(): Project|JobSite
    {
        return $this->project;
    }

    public function render()
    {
        return view('livewire.project.project-team')->layout('components.layouts.app');
    }
}
