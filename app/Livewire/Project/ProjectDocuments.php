<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\ManagesDocuments;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * The project's file repository: folders, documents, versions and share
 * links, for the project itself and for each of its job sites.
 *
 * See docs/file-repository-plan.md.
 */
class ProjectDocuments extends Component
{
    use ManagesDocuments, WithFileUploads, WithPagination;

    public Project $project;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'locationFilter' => ['except' => 'project'],
        'folderId' => ['except' => null],
        'viewMode' => ['except' => 'list'],
    ];

    public function mount(Project $project): void
    {
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
        return view('livewire.project.project-documents', [
            'jobSites' => $this->project->jobSites()->orderBy('job_site_name')->get(),
        ])->layout('components.layouts.app');
    }
}
