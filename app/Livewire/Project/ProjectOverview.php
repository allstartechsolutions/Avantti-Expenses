<?php

namespace App\Livewire\Project;

use App\Models\ChangeOrder;
use App\Models\DailyReport;
use App\Models\DailyReportImage;
use App\Models\DailyReportManpower;
use App\Models\Expense;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ProjectOverview extends Component
{
    public Project $project;

    // Delete Project modal
    public $showDeleteProjectModal = false;
    public $deleteProjectData = [];

    public function mount(Project $project): void
    {
        $this->project = $project->load(['client', 'createdBy', 'projectManager']);
    }

    public function confirmDeleteProject()
    {
        $project = Project::withCount([
            'jobSites',
            'expenses',
            'changeOrders',
            'dailyReports',
            'budgets',
        ])->findOrFail($this->project->id);

        $purchaseOrdersCount = PurchaseOrder::where('project_id', $this->project->id)->count();

        $this->deleteProjectData = [
            'name' => $project->project_name,
            'job_sites' => $project->job_sites_count,
            'expenses' => $project->expenses_count,
            'change_orders' => $project->change_orders_count,
            'daily_reports' => $project->daily_reports_count,
            'budgets' => $project->budgets_count,
            'purchase_orders' => $purchaseOrdersCount,
        ];

        $this->showDeleteProjectModal = true;
        $this->dispatch('open-modal', 'delete-project-modal');
    }

    public function deleteProject()
    {
        DB::transaction(function () {
            $this->cleanupProjectFiles($this->project->id);
            $this->project->delete();
        });

        session()->flash('message', __('Project deleted successfully!'));
        return $this->redirect(route('projects.index'), navigate: true);
    }

    public function cancelDeleteProject()
    {
        $this->showDeleteProjectModal = false;
        $this->deleteProjectData = [];
        $this->dispatch('close-modal', 'delete-project-modal');
    }

    protected function cleanupProjectFiles($projectId)
    {
        // Delete expense receipt files
        $receiptPaths = Expense::where('project_id', $projectId)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path');

        foreach ($receiptPaths as $path) {
            Storage::delete($path);
        }

        // Delete change order files
        $changeOrderPaths = ChangeOrder::where('project_id', $projectId)
            ->whereNotNull('file_path')
            ->pluck('file_path');

        foreach ($changeOrderPaths as $path) {
            Storage::delete($path);
        }

        // Delete purchase order receipt files
        $poPaths = PurchaseOrder::where('project_id', $projectId)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path');

        foreach ($poPaths as $path) {
            Storage::delete($path);
        }

        // Delete daily report images (polymorphic - won't cascade)
        $dailyReportIds = DailyReport::where('project_id', $projectId)->pluck('id');

        if ($dailyReportIds->isNotEmpty()) {
            $imagePaths = DailyReportImage::whereIn('imageable_id', $dailyReportIds)
                ->where('imageable_type', DailyReport::class)
                ->pluck('file_path');

            foreach ($imagePaths as $path) {
                Storage::delete($path);
            }

            // Also get manpower log images
            $manpowerIds = DailyReportManpower::whereIn('daily_report_id', $dailyReportIds)->pluck('id');

            if ($manpowerIds->isNotEmpty()) {
                $manpowerImagePaths = DailyReportImage::whereIn('imageable_id', $manpowerIds)
                    ->where('imageable_type', DailyReportManpower::class)
                    ->pluck('file_path');

                foreach ($manpowerImagePaths as $path) {
                    Storage::delete($path);
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.project.project-overview')
            ->layout('components.layouts.app');
    }
}
