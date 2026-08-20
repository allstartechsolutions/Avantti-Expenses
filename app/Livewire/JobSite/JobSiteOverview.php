<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\ChangeOrder;
use App\Models\DailyReport;
use App\Models\DailyReportImage;
use App\Models\DailyReportManpower;
use App\Models\Expense;
use App\Models\JobSite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class JobSiteOverview extends Component
{
    use AuthorizesAbility;

    public JobSite $jobSite;

    // Delete Job Site modal
    public $showDeleteJobSiteModal = false;
    public $deleteJobSiteData = [];

    public function mount(JobSite $jobSite): void
    {
        $this->jobSite = $jobSite->load([
            'project',
            'createdBy',
            'supervisor',
            'supervisorHistories.changedBy',
            'supervisorHistories.oldSupervisor',
            'supervisorHistories.newSupervisor',
        ]);
    }

    public function confirmDeleteJobSite()
    {
        $this->authorizeAbility('projects.delete', $this->jobSite);

        $jobSite = JobSite::withCount([
            'expenses',
            'changeOrders',
            'dailyReports',
        ])->findOrFail($this->jobSite->id);

        $hasBudget = $jobSite->budget()->exists() ? 1 : 0;

        $this->deleteJobSiteData = [
            'name' => $jobSite->job_site_name,
            'expenses' => $jobSite->expenses_count,
            'change_orders' => $jobSite->change_orders_count,
            'daily_reports' => $jobSite->daily_reports_count,
            'budgets' => $hasBudget,
        ];

        $this->showDeleteJobSiteModal = true;
        $this->dispatch('open-modal', 'delete-jobsite-modal');
    }

    public function deleteJobSite()
    {
        $this->authorizeAbility('projects.delete', $this->jobSite);

        $projectId = $this->jobSite->project_id;

        DB::transaction(function () {
            $this->cleanupJobSiteFiles($this->jobSite->id);
            $this->jobSite->delete();
        });

        session()->flash('message', __('Job site deleted successfully!'));
        return $this->redirect(route('projects.jobsites', $projectId), navigate: true);
    }

    public function cancelDeleteJobSite()
    {
        $this->showDeleteJobSiteModal = false;
        $this->deleteJobSiteData = [];
        $this->dispatch('close-modal', 'delete-jobsite-modal');
    }

    protected function cleanupJobSiteFiles($jobSiteId)
    {
        // Delete expense receipt files
        $receiptPaths = Expense::where('job_site_id', $jobSiteId)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path');

        foreach ($receiptPaths as $path) {
            Storage::delete($path);
        }

        // Delete change order files
        $changeOrderPaths = ChangeOrder::where('job_site_id', $jobSiteId)
            ->whereNotNull('file_path')
            ->pluck('file_path');

        foreach ($changeOrderPaths as $path) {
            Storage::delete($path);
        }

        // Delete daily report images (polymorphic - won't cascade)
        $dailyReportIds = DailyReport::where('job_site_id', $jobSiteId)->pluck('id');

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
        $changeOrders = $this->jobSite->changeOrders()->get();
        $totalChangeOrdersAmount = $changeOrders->sum('amount');

        $expenses = $this->jobSite->expenses()->get();
        $totalExpensesAmount = $expenses->sum('total_amount');

        $contracts = $this->jobSite->contracts()->committed()->with(['changeOrders', 'payments'])->get();
        $totalContractsAdjusted = 0;
        $totalContractsPaid = 0;
        foreach ($contracts as $contract) {
            $totalContractsAdjusted += $contract->getAdjustedAmount();
            $totalContractsPaid += $contract->getAmountPaid();
        }
        $totalContractsUnpaid = round($totalContractsAdjusted - $totalContractsPaid, 2);

        return view('livewire.job-site.job-site-overview', [
            'changeOrders' => $changeOrders,
            'totalChangeOrdersAmount' => $totalChangeOrdersAmount,
            'expenses' => $expenses,
            'totalExpensesAmount' => $totalExpensesAmount,
            'contracts' => $contracts,
            'totalContractsAdjusted' => $totalContractsAdjusted,
            'totalContractsPaid' => $totalContractsPaid,
            'totalContractsUnpaid' => $totalContractsUnpaid,
        ])->layout('components.layouts.app');
    }
}
