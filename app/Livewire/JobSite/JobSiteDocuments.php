<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\ManagesDocuments;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * The job site's file repository — the same screen as the project's, scoped
 * to this site (docs/project-jobsite-parity-rule.md).
 */
class JobSiteDocuments extends Component
{
    use ManagesDocuments, WithFileUploads, WithPagination;

    public JobSite $jobSite;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'folderId' => ['except' => null],
        'viewMode' => ['except' => 'list'],
    ];

    public function mount(JobSite $jobSite): void
    {
        $this->jobSite = $jobSite->load('project');
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
        return view('livewire.job-site.job-site-documents')
            ->layout('components.layouts.app');
    }
}
