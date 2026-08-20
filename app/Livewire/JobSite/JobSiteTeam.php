<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\ManagesTeam;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;

/**
 * Who is on this job site. Same screen as the project level, plus the people
 * who reach this site through the project — shown separately, because access
 * given here overrides what the project gives them.
 */
class JobSiteTeam extends Component
{
    use ManagesTeam;

    public JobSite $jobSite;

    public function mount(JobSite $jobSite): void
    {
        $this->jobSite = $jobSite->load('project');

        $this->authorizeAbility('team.view', $jobSite);
    }

    protected function contextScope(): Project|JobSite
    {
        return $this->jobSite;
    }

    public function render()
    {
        return view('livewire.job-site.job-site-team')->layout('components.layouts.app');
    }
}
