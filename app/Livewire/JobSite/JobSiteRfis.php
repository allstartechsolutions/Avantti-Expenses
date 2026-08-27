<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\ManagesRfis;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One job site's RFIs. The same list as the project page, fixed to this site.
 *
 * See docs/project-jobsite-parity-rule.md — the two levels ship together and
 * share their query, so an improvement to one is an improvement to both.
 */
class JobSiteRfis extends Component
{
    use ManagesRfis, WithPagination;

    public JobSite $jobSite;

    protected $queryString = [
        'rfiSearch' => ['except' => ''],
        'rfiStatusFilter' => ['except' => 'live'],
        'rfiDisciplineFilter' => ['except' => 'all'],
        'rfiBallInCourtFilter' => ['except' => 'all'],
        'rfiOverdueOnly' => ['except' => false],
        'rfiImpactOnly' => ['except' => false],
    ];

    public function mount(JobSite $jobSite): void
    {
        $this->authorizeAbility('rfis.view', $jobSite);

        $this->jobSite = $jobSite;
    }

    protected function contextProject(): Project
    {
        return $this->jobSite->project;
    }

    protected function contextJobSite(): ?JobSite
    {
        return $this->jobSite;
    }

    public function render()
    {
        return view('livewire.job-site.job-site-rfis', [
            'rfis' => $this->rfis(),
            'summary' => $this->rfiSummary(),
            'disciplineOptions' => $this->rfiDisciplineOptions(),
            'ballInCourtOptions' => $this->rfiBallInCourtOptions(),
        ])->layout('components.layouts.app');
    }
}
