<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\ManagesApprovals;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The project's approvals — its own, and those of every job site under it.
 *
 * See docs/RFI-Submittals-modules.md phase 5.
 */
class ProjectApprovals extends Component
{
    use ManagesApprovals, WithPagination;

    public Project $project;

    protected $queryString = [
        'approvalSearch' => ['except' => ''],
        'approvalStatusFilter' => ['except' => 'live'],
        'approvalTypeFilter' => ['except' => 'all'],
        'approvalLocationFilter' => ['except' => 'all'],
        'approvalReviewerFilter' => ['except' => 'all'],
        'approvalOverdueOnly' => ['except' => false],
        'approvalCertificateAlertsOnly' => ['except' => false],
    ];

    public function mount(Project $project): void
    {
        $this->authorizeAbility('approvals.view', $project);

        $this->project = $project;
    }

    protected function contextProject(): Project
    {
        return $this->project;
    }

    protected function contextJobSite(): ?JobSite
    {
        return null;
    }

    public function render()
    {
        return view('livewire.project.project-approvals', [
            'approvals' => $this->approvals(),
            'summary' => $this->approvalSummary(),
            'jobSites' => $this->project->jobSites()->orderBy('job_site_name')->get(['id', 'job_site_name']),
            'typeOptions' => $this->approvalTypeOptions(),
            'reviewerOptions' => $this->approvalReviewerOptions(),
        ])->layout('components.layouts.app');
    }
}
