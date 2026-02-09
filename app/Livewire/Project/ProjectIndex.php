<?php

namespace App\Livewire\Project;

use App\Enums\ProjectStatus;
use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\DailyReportImage;
use App\Models\Expense;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $clientFilter = '';
    public $perPage = 10;

    // Delete modal
    public $showDeleteModal = false;
    public $deletingProjectId = null;
    public $deleteProjectData = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'clientFilter' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingClientFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'clientFilter']);
        $this->resetPage();
    }

    public function confirmDeleteProject($projectId)
    {
        $project = Project::withCount([
            'jobSites',
            'expenses',
            'changeOrders',
            'dailyReports',
            'budgets',
        ])->findOrFail($projectId);

        $purchaseOrdersCount = PurchaseOrder::where('project_id', $projectId)->count();

        $this->deletingProjectId = $projectId;
        $this->deleteProjectData = [
            'name' => $project->project_name,
            'job_sites' => $project->job_sites_count,
            'expenses' => $project->expenses_count,
            'change_orders' => $project->change_orders_count,
            'daily_reports' => $project->daily_reports_count,
            'budgets' => $project->budgets_count,
            'purchase_orders' => $purchaseOrdersCount,
        ];

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'delete-project-modal');
    }

    public function deleteProject()
    {
        $project = Project::findOrFail($this->deletingProjectId);

        DB::transaction(function () use ($project) {
            // Clean up files before cascade delete (Eloquent events won't fire on cascade)
            $this->cleanupProjectFiles($project->id);

            $project->delete();
        });

        $this->showDeleteModal = false;
        $this->deletingProjectId = null;
        $this->deleteProjectData = [];

        session()->flash('message', 'Project deleted successfully!');
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

        // Delete daily report images
        $dailyReportIds = \App\Models\DailyReport::where('project_id', $projectId)
            ->pluck('id');

        if ($dailyReportIds->isNotEmpty()) {
            $imagePaths = DailyReportImage::whereIn('imageable_id', $dailyReportIds)
                ->where('imageable_type', \App\Models\DailyReport::class)
                ->pluck('file_path');

            foreach ($imagePaths as $path) {
                Storage::delete($path);
            }

            // Also get manpower log images
            $manpowerIds = \App\Models\DailyReportManpower::whereIn('daily_report_id', $dailyReportIds)
                ->pluck('id');

            if ($manpowerIds->isNotEmpty()) {
                $manpowerImagePaths = DailyReportImage::whereIn('imageable_id', $manpowerIds)
                    ->where('imageable_type', \App\Models\DailyReportManpower::class)
                    ->pluck('file_path');

                foreach ($manpowerImagePaths as $path) {
                    Storage::delete($path);
                }
            }
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deletingProjectId = null;
        $this->deleteProjectData = [];
        $this->dispatch('close-modal', 'delete-project-modal');
    }

    public function render()
    {
        $projects = Project::query()
            ->with(['client', 'createdBy', 'jobSites'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('project_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_person', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhereHas('client', function($q) {
                          $q->where('company_name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->clientFilter, function ($query) {
                $query->where('client_id', $this->clientFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $clients = Client::orderBy('company_name')->get();
        $statuses = ProjectStatus::cases();

        return view('livewire.project.project-index', [
            'projects' => $projects,
            'clients' => $clients,
            'statuses' => $statuses,
        ])->layout('components.layouts.app');
    }
}
