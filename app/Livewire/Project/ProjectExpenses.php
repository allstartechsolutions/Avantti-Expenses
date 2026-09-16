<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\HandlesExpensePaymentActions;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Project;
use Livewire\Component;

class ProjectExpenses extends Component
{
    use AuthorizesAbility;
    use HandlesExpensePaymentActions;

    public Project $project;

    // Filters
    public $expenseSearch = '';
    public $expenseLocationFilter = 'all';
    public $expenseStatusFilter = 'all';

    /** Narrow the list to one cost code, matched by code across the project's budgets. */
    public $expenseCostCodeFilter = 'all';

    public function mount(Project $project): void
    {
        $this->authorizeAbility('expenses.view', $project);

        $this->project = $project;
    }

    /**
     * Every expense on this screen belongs to the project or to one of its job
     * sites, and the answer has to be given against the record rather than the
     * screen: a job site can carry a membership of its own that overrides the
     * project's.
     */
    protected function expenseInScope(int $expenseId): Expense
    {
        return Expense::where('project_id', $this->project->id)->findOrFail($expenseId);
    }

    protected function paymentInScope(int $paymentId): ExpensePayment
    {
        return ExpensePayment::whereHas(
            'expense',
            fn ($q) => $q->where('project_id', $this->project->id)
        )->findOrFail($paymentId);
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        // Expenses query with filters
        $expensesQuery = $this->project->expenses()
            ->with(['jobSite', 'supplier', 'createdBy', 'payments', 'items.budgetItem']);

        // Apply location filter
        if ($this->expenseLocationFilter === 'project') {
            $expensesQuery->whereNull('job_site_id');
        } elseif ($this->expenseLocationFilter !== 'all' && is_numeric($this->expenseLocationFilter)) {
            $expensesQuery->where('job_site_id', $this->expenseLocationFilter);
        }

        // Apply status filter
        if ($this->expenseStatusFilter !== 'all') {
            $expensesQuery->where('status', $this->expenseStatusFilter);
        }

        // Apply cost code filter
        if ($this->expenseCostCodeFilter !== 'all') {
            $expensesQuery->whereHas('items.budgetItem', function ($q) {
                $q->where('code', $this->expenseCostCodeFilter);
            });
        }

        // Apply search filter
        if ($this->expenseSearch) {
            $expensesQuery->where(function ($query) {
                $query->where('notes', 'like', '%' . $this->expenseSearch . '%')
                    ->orWhereHas('items', function ($itemQuery) {
                        $itemQuery->where('item_name', 'like', '%' . $this->expenseSearch . '%');
                    })
                    ->orWhereHas('supplier', function ($supplierQuery) {
                        $supplierQuery->where('name', 'like', '%' . $this->expenseSearch . '%');
                    });
            });
        }

        $expenses = $expensesQuery->orderBy('expense_date', 'desc')->get();
        $totalExpensesAmount = $expenses->sum('total_amount');
        $totalPaidAmount = $expenses->sum(fn ($e) => $e->getPaidAmount());
        $totalPendingAmount = $expenses->sum(fn ($e) => $e->getPendingAmount());

        $costCodes = BudgetItem::whereIn('budget_id', Budget::where('project_id', $this->project->id)->pluck('id'))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->unique('code')
            ->values();

        return view('livewire.project.project-expenses', [
            'expenses' => $expenses,
            'jobSites' => $jobSites,
            'costCodes' => $costCodes,
            'totalExpensesAmount' => $totalExpensesAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalPendingAmount' => $totalPendingAmount,
        ])->layout('components.layouts.app');
    }
}
