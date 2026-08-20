<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\ListsScopedTasks;
use App\Livewire\Concerns\ManagesTasks;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tasks on a project — its own and every one of its job sites'.
 *
 * The job-site page is the same screen with the location fixed
 * (docs/project-jobsite-parity-rule.md); both share ListsScopedTasks and one
 * Blade partial, so they cannot drift apart.
 */
class ProjectTasks extends Component
{
    use ListsScopedTasks, ManagesTasks;

    public Project $project;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'priorityFilter' => ['except' => ''],
        'ownerFilter' => ['except' => ''],
        'jobSiteFilter' => ['except' => ''],
        'trackingFilter' => ['except' => ''],
        'showClosed' => ['except' => false],
    ];

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    protected function taskContextProject(): ?Project
    {
        return $this->project;
    }

    protected function taskContextJobSite(): ?JobSite
    {
        return null;
    }

    /** The job sites a task here could belong to, for the filter. */
    #[Computed]
    public function scopeJobSites(): Collection
    {
        return JobSite::where('project_id', $this->project->id)
            ->orderBy('job_site_name')
            ->get(['id', 'job_site_name']);
    }

    public function render()
    {
        return view('livewire.project.project-tasks');
    }
}
