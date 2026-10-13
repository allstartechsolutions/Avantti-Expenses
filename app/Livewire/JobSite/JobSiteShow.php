<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\HandlesExpensePaymentActions;
use App\Livewire\Concerns\ManagesChangeOrders;
use App\Models\ChangeOrder;
use App\Models\DailyReport;
use App\Models\DailyReportImage;
use App\Models\DailyReportManpower;
use App\Models\EquipmentAssignment;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\JobSite;
use App\Models\PurchaseOrder;
use App\Services\CostCodeLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class JobSiteShow extends Component
{
    use AuthorizesAbility, ManagesChangeOrders, WithFileUploads;
    use HandlesExpensePaymentActions;

    public JobSite $jobSite;

    public $activeTab = 'overview';

    // Delete Job Site modal
    public $showDeleteJobSiteModal = false;

    public $deleteJobSiteData = [];

    // Expense list filters — the view modal and payment actions come from
    // HandlesExpensePaymentActions, the same code the project screen runs.
    public $expenseSearch = '';

    public $expenseStatusFilter = 'all';

    /** Narrow the expense list to one cost code. */
    public $expenseCostCodeFilter = 'all';

    public $activeNavTab = 'overview';

    public function mount(JobSite $jobSite)
    {
        $this->jobSite = $jobSite->load('project');

        // Detect which section page we're on from the URL
        $path = request()->path();
        $tabMap = [
            'expenses' => 'expenses',
            'change-orders' => 'changeorders',
            'purchase-orders' => 'purchaseorders',
            'daily-reports' => 'dailyreports',
            'budget' => 'budget',
        ];

        foreach ($tabMap as $segment => $tab) {
            if (str_ends_with($path, '/'.$segment)) {
                $this->activeTab = $tab;
                $this->activeNavTab = $segment;
                break;
            }
        }

        $this->authorizeTab($this->activeTab);

        // "Create change order" on an RFI arrives here carrying ?fromRfi=.
        if ($this->activeTab === 'changeorders') {
            $this->applyChangeOrderQueryIntent();
        }
    }

    public function setActiveTab($tab)
    {
        $this->authorizeTab($tab);

        $this->activeTab = $tab;
    }

    /**
     * This one component serves five job-site tabs, so the guard belongs to the
     * tab rather than to the route: switching tab is not a fresh request, and
     * `setActiveTab` can be called straight from the browser.
     *
     * Only the swept modules answer here. The rest keep their old rules until
     * their own pass, exactly as the legacy bridge intends.
     */
    protected function authorizeTab(string $tab): void
    {
        $abilities = [
            'expenses' => 'expenses.view',
            'purchaseorders' => 'purchase-orders.view',
            'changeorders' => 'change-orders.view',
            'dailyreports' => 'daily-reports.view',
            'budget' => 'budget.view',
        ];

        if (isset($abilities[$tab])) {
            $this->authorizeAbility($abilities[$tab], $this->jobSite);
        }
    }

    /** An expense of THIS job site, or a 404. */
    protected function expenseInScope(int $expenseId): Expense
    {
        return Expense::where('job_site_id', $this->jobSite->id)->findOrFail($expenseId);
    }

    /** An installment of an expense of THIS job site, or a 404. */
    protected function paymentInScope(int $paymentId): ExpensePayment
    {
        return ExpensePayment::whereHas(
            'expense',
            fn ($q) => $q->where('job_site_id', $this->jobSite->id)
        )->findOrFail($paymentId);
    }

    protected function changeOrderProjectId(): int
    {
        return $this->jobSite->project_id;
    }

    /** This screen only ever shows and writes change orders for its own job site. */
    protected function changeOrderPinnedJobSiteId(): int|false
    {
        return $this->jobSite->id;
    }

    protected function afterChangeOrderSaved(): void
    {
        $this->jobSite->refresh();
    }

    // =========================================================================
    // DELETE JOB SITE
    // =========================================================================

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
            EquipmentAssignment::closeFor($this->jobSite);
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
        $changeOrders = $this->changeOrderQuery()
            ->orderByDesc('requested_date')
            ->orderByDesc('id')
            ->get();

        // Expenses
        $expensesQuery = $this->jobSite->expenses()->with(['supplier', 'createdBy', 'payments', 'items.budgetItem']);

        // Apply cost code filter
        if ($this->expenseCostCodeFilter !== 'all') {
            $expensesQuery->whereHas('items.budgetItem', function ($q) {
                $q->where('code', $this->expenseCostCodeFilter);
            });
        }

        // Apply status filter
        if ($this->expenseStatusFilter !== 'all') {
            $expensesQuery->where('status', $this->expenseStatusFilter);
        }

        if ($this->expenseSearch) {
            $expensesQuery->where(function ($query) {
                $query->where('item_name', 'like', '%'.$this->expenseSearch.'%')
                    ->orWhere('notes', 'like', '%'.$this->expenseSearch.'%')
                    ->orWhereHas('items', function ($itemQuery) {
                        $itemQuery->where('item_name', 'like', '%'.$this->expenseSearch.'%');
                    })
                    ->orWhereHas('supplier', function ($supplierQuery) {
                        $supplierQuery->where('name', 'like', '%'.$this->expenseSearch.'%');
                    });
            });
        }

        $expenses = $expensesQuery->orderBy('expense_date', 'desc')->get();
        $totalExpensesAmount = $expenses->sum('total_amount');

        // Calculate payment totals
        $totalPaidAmount = $expenses->sum(fn ($e) => $e->getPaidAmount());
        $totalPendingAmount = $expenses->sum(fn ($e) => $e->getPendingAmount());

        // Daily Reports
        $dailyReports = $this->jobSite->dailyReports()
            ->with(['preparedBy', 'tasks.images'])
            ->orderBy('report_date', 'desc')
            ->get();

        // Budget
        $budget = $this->jobSite->budget?->load(['sourceTemplate', 'parentItems']);

        // Only the budget tab shows these figures, and building them walks every
        // contract, expense and purchase order for the location.
        $budgetLedger = ($budget && $this->activeTab === 'budget')
            ? CostCodeLedger::for($budget)
            : null;

        // Purchase Orders
        $purchaseOrders = PurchaseOrder::where('job_site_id', $this->jobSite->id)
            ->with(['supplier', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $purchaseOrderStats = [
            'total' => $purchaseOrders->count(),
            'pending' => $purchaseOrders->where('status', 'pending')->count(),
            'approved' => $purchaseOrders->where('status', 'approved')->count(),
            'totalAmount' => $purchaseOrders->sum('total_amount'),
            'approvedAmount' => $purchaseOrders->where('status', 'approved')->sum('total_amount'),
        ];

        return view('livewire.job-site.job-site-show', [
            'changeOrders' => $changeOrders,
            'changeOrderSummary' => $this->changeOrderSummary($changeOrders),
            'coBudget' => $this->changeOrderBudget(),
            'coLineSuggestions' => $this->changeOrderLineSearchResults(),
            'changeOrderRecord' => $this->editingChangeOrder
                ? ChangeOrder::with(['createdBy', 'approvedBy'])->find($this->editingChangeOrder)
                : null,
            'expenses' => $expenses,
            'totalExpensesAmount' => $totalExpensesAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalPendingAmount' => $totalPendingAmount,
            'dailyReports' => $dailyReports,
            'budget' => $budget,
            'expenseCostCodes' => $budget
                ? $budget->items()->orderBy('code')->get(['id', 'code', 'name'])
                : collect(),
            'budgetTotals' => $budgetLedger?->totals(),
            'budgetLedgerRows' => $budgetLedger?->rowsByItem() ?? [],
            'purchaseOrders' => $purchaseOrders,
            'purchaseOrderStats' => $purchaseOrderStats,
        ])->layout('components.layouts.app');
    }
}
