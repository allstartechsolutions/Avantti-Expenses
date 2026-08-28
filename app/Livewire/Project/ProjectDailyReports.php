<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Project;
use Livewire\Component;

class ProjectDailyReports extends Component
{
    use AuthorizesAbility;

    public Project $project;

    // Filters
    public $dailyReportSearch = '';
    public $dailyReportLocationFilter = 'all';

    public function mount(Project $project): void
    {
        $this->authorizeAbility('daily-reports.view', $project);

        $this->project = $project;
    }

    /**
     * Destroy a daily report.
     *
     * Guarded against the report itself, not the page: the id came from the
     * browser, and belonging to this project is something to check rather than
     * assume. Only an unlocked one — a locked report has been signed off and
     * read, and deleting it removes a day from the project's history.
     */
    public function deleteDailyReport(int $reportId): void
    {
        $report = $this->project->dailyReports()->findOrFail($reportId);

        $this->authorizeAbility('daily-reports.delete', $report->jobSite ?? $this->project);

        abort_unless(
            $report->canBeDeleted(),
            403,
            __('This daily report has been locked. It is the record of that day and cannot be deleted.'),
        );

        $date = $report->report_date?->format(config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y');

        $report->delete();

        session()->flash('message', __('The daily report for :date was deleted.', ['date' => $date]));
    }

    /** Read by the view before it renders the button. Never the guard itself. */
    public function canDelete(\App\Models\DailyReport $report): bool
    {
        return $report->canBeDeleted()
            && $this->allowsAbility('daily-reports.delete', $report->jobSite ?? $this->project);
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
