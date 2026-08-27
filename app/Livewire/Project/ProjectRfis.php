<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\ManagesRfis;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The project's RFIs — its own, and those of every job site under it.
 *
 * See docs/RFI-Submittals-modules.md phase 3.
 */
class ProjectRfis extends Component
{
    use ManagesRfis, WithPagination;

    public Project $project;

    protected $queryString = [
        'rfiSearch' => ['except' => ''],
        'rfiStatusFilter' => ['except' => 'live'],
        'rfiDisciplineFilter' => ['except' => 'all'],
        'rfiLocationFilter' => ['except' => 'all'],
        'rfiBallInCourtFilter' => ['except' => 'all'],
        'rfiOverdueOnly' => ['except' => false],
        'rfiImpactOnly' => ['except' => false],
    ];

    public function mount(Project $project): void
    {
        $this->authorizeAbility('rfis.view', $project);

        $this->project = $project;
    }

    protected function contextProject(): Project
    {
        return $this->project;
    }

    /** The project page covers every location, so none is fixed. */
    protected function contextJobSite(): ?JobSite
    {
        return null;
    }

    public function render()
    {
        return view('livewire.project.project-rfis', [
            'rfis' => $this->rfis(),
            'summary' => $this->rfiSummary(),
            'jobSites' => $this->project->jobSites()->orderBy('job_site_name')->get(['id', 'job_site_name']),
            'disciplineOptions' => $this->rfiDisciplineOptions(),
            'ballInCourtOptions' => $this->rfiBallInCourtOptions(),
        ])->layout('components.layouts.app');
    }
}
