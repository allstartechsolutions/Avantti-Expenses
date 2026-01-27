<?php

namespace App\Livewire\Project;

use App\Models\Project;
use Livewire\Component;

class ProjectDailyReports extends Component
{
    public Project $project;

    // Filters
    public $dailyReportSearch = '';
    public $dailyReportLocationFilter = 'all';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        // Daily Reports query with filters
        $dailyReportsQuery = $this->project->dailyReports()->with(['jobSite', 'preparedBy', 'tasks']);

        // Apply location filter
        if ($this->dailyReportLocationFilter === 'project') {
            $dailyReportsQuery->whereNull('job_site_id');
        } elseif ($this->dailyReportLocationFilter !== 'all' && is_numeric($this->dailyReportLocationFilter)) {
            $dailyReportsQuery->where('job_site_id', $this->dailyReportLocationFilter);
        }

        // Apply search filter
        if ($this->dailyReportSearch) {
            $dailyReportsQuery->where(function ($query) {
                $query->whereHas('tasks', function ($taskQuery) {
                    $taskQuery->where('description', 'like', '%' . $this->dailyReportSearch . '%');
                })
                ->orWhereHas('preparedBy', function ($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->dailyReportSearch . '%');
                });
            });
        }

        $dailyReports = $dailyReportsQuery->orderBy('report_date', 'desc')->get();

        return view('livewire.project.project-daily-reports', [
            'dailyReports' => $dailyReports,
            'jobSites' => $jobSites,
        ])->layout('components.layouts.app');
    }
}
