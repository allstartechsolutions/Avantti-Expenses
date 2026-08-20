<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\ListsScopedTasks;
use App\Livewire\Concerns\ManagesTasks;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tasks on one job site. The same screen as the project level with the
 * location fixed to this site — see docs/project-jobsite-parity-rule.md.
 */
class JobSiteTasks extends Component
{
    use ListsScopedTasks, ManagesTasks;

    public JobSite $jobSite;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'priorityFilter' => ['except' => ''],
        'ownerFilter' => ['except' => ''],
        'trackingFilter' => ['except' => ''],
        'showClosed' => ['except' => false],
    ];

    public function mount(JobSite $jobSite): void
    {
        $this->jobSite = $jobSite->load('project');
    }

    protected function taskContextProject(): ?Project
    {
        return $this->jobSite->project;
    }

    protected function taskContextJobSite(): ?JobSite
    {
        return $this->jobSite;
    }

    /** No job-site filter on a job-site page: there is only this one. */
    #[Computed]
    public function scopeJobSites(): Collection
    {
        return collect();
    }

    public function render()
    {
        return view('livewire.job-site.job-site-tasks');
    }
}
