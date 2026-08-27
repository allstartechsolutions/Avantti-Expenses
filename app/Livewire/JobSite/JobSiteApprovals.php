<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\ManagesApprovals;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One job site's approvals. The same list as the project page, fixed to this
 * site — the two levels ship together
 * (docs/project-jobsite-parity-rule.md).
 */
class JobSiteApprovals extends Component
{
    use ManagesApprovals, WithPagination;

    public JobSite $jobSite;

    protected $queryString = [
        'approvalSearch' => ['except' => ''],
        'approvalStatusFilter' => ['except' => 'live'],
        'approvalTypeFilter' => ['except' => 'all'],
        'approvalReviewerFilter' => ['except' => 'all'],
        'approvalOverdueOnly' => ['except' => false],
        'approvalCertificateAlertsOnly' => ['except' => false],
    ];

    public function mount(JobSite $jobSite): void
    {
        $this->authorizeAbility('approvals.view', $jobSite);

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
        return view('livewire.job-site.job-site-approvals', [
            'approvals' => $this->approvals(),
            'summary' => $this->approvalSummary(),
            'typeOptions' => $this->approvalTypeOptions(),
            'reviewerOptions' => $this->approvalReviewerOptions(),
        ])->layout('components.layouts.app');
    }
}
