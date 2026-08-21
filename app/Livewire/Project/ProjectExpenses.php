<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ProjectExpenses extends Component
{
    use AuthorizesAbility;

    public Project $project;

    // Filters
    public $expenseSearch = '';
    public $expenseLocationFilter = 'all';
    public $expenseStatusFilter = 'all';

    // View modal
    public $showExpenseModal = false;
    public $viewingExpense = null;

    // View modal data
    public $expense_date = '';
    public $expense_job_site_id = null;
    public $expense_supplier_id = null;
    public $supplierSearch = '';
    public $expense_notes = '';
    public $expense_total_amount = 0;
    public $expense_status = '';
    public $expense_payment_method = null;
    public $expense_is_auto_payment = false;
    public $expense_has_installments = false;
    public $expense_total_installments = 0;
    public $expense_payment_frequency = '';
    public $expense_payment_due_date = '';
    public $expense_paid_date = '';
    public $existingReceiptPath = null;
    public $expenseItems = [];
    public $expenseHistory = [];

    // Mark-as-paid confirmation state (inline date picker)
    public $markPaidType = null; // 'expense' or 'payment'
    public $markPaidId = null;
    public $markPaidDate = '';

    // Installment due-date editing state
    public $editDueDateId = null;
    public $editDueDate = '';

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

    public function openExpenseViewModal(int $expenseId): void
    {
        $expense = Expense::with(['payments.paidBy', 'paidBy', 'items.budgetItem', 'items.catalogItem', 'supplier', 'jobSite'])
            ->where('project_id', $this->project->id)
            ->findOrFail($expenseId);

        $this->authorizeAbility('expenses.view', $expense);

        $this->viewingExpense = $expense;
        $this->expense_job_site_id = $expense->job_site_id;
        $this->expense_supplier_id = $expense->supplier_id;
        $this->supplierSearch = $expense->supplier?->name ?? '';
        $this->expense_notes = $expense->notes;
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->existingReceiptPath = $expense->receipt_path;
        $this->expense_total_amount = $expense->total_amount;

        // Load expense items
        $this->expenseItems = [];
        if ($expense->items->count() > 0) {
            foreach ($expense->items as $item) {
                $this->expenseItems[] = [
                    'id' => $item->id,
                    'budget_item_id' => $item->budget_item_id,
                    'cost_code' => $item->cost_code_display,
                    'catalog_item_id' => $item->catalog_item_id,
                    'item_name' => $item->item_name,
                    'item_type' => $item->item_type,
                    'description' => $item->description ?? '',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit ?? '',
                    'unit_price' => $item->unit_price,
                    'total_amount' => $item->total_amount,
                ];
            }
        }

        // Payment fields
        $this->expense_status = $expense->status;
        $this->expense_payment_method = $expense->payment_method;
        $this->expense_is_auto_payment = $expense->is_auto_payment;
        $this->expense_has_installments = $expense->isInstallment();
        $this->expense_total_installments = $expense->total_installments;
        $this->expense_payment_frequency = $expense->payment_frequency;
        $this->expense_payment_due_date = $expense->payment_due_date?->format('Y-m-d');
        $this->expense_paid_date = $expense->paid_date?->format('Y-m-d');

        $this->expenseHistory = $expense->changeHistories()
            ->with(['changedBy', 'expensePayment'])
            ->get()
            ->map(fn ($h) => [
                'label' => $h->getActionLabel(),
                'color' => $h->getActionColor(),
                'user' => $h->changedBy?->name,
                'date' => $h->created_at->format('M d, Y H:i'),
                'changes' => $h->changes,
            ])
            ->toArray();

        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-view-modal');
    }

    public function closeExpenseModal(): void
    {
        $this->showExpenseModal = false;
        $this->viewingExpense = null;
        $this->expenseHistory = [];
        $this->cancelMarkPaid();
        $this->cancelEditDueDate();
        $this->dispatch('close-modal', 'expense-view-modal');
    }

    public function startMarkPaid(string $type, int $id): void
    {
        $this->authorizeAbility('expenses.pay', $type === 'payment'
            ? $this->paymentInScope($id)
            : $this->expenseInScope($id));

        $this->markPaidType = $type;
        $this->markPaidId = $id;
        $this->markPaidDate = now()->format('Y-m-d');
    }

    public function cancelMarkPaid(): void
    {
        $this->reset(['markPaidType', 'markPaidId', 'markPaidDate']);
    }

    public function confirmMarkPaid(): void
    {
        $this->validate(['markPaidDate' => 'required|date']);

        $paidDate = \Carbon\Carbon::parse($this->markPaidDate);

        if ($this->markPaidType === 'payment') {
            $payment = $this->paymentInScope((int) $this->markPaidId);
            $this->authorizeAbility('expenses.pay', $payment);
            $payment->markAsPaid(null, $paidDate);
            session()->flash('message', __('Payment marked as paid.'));
        } else {
            $expense = $this->expenseInScope((int) $this->markPaidId);
            $this->authorizeAbility('expenses.pay', $expense);
            if ($expense->isOneTime() && $expense->status !== 'paid') {
                $expense->markAsPaid(null, $paidDate);
            }
            session()->flash('message', __('Expense marked as paid.'));
        }

        $this->cancelMarkPaid();

        // Refresh the viewing expense
        if ($this->viewingExpense) {
            $this->viewingExpense->refresh();
            $this->viewingExpense->load('payments.paidBy');
        }
    }

    public function startEditDueDate(int $paymentId): void
    {
        $payment = $this->paymentInScope($paymentId);
        $this->authorizeAbility('expenses.edit', $payment);
        $this->editDueDateId = $payment->id;
        $this->editDueDate = $payment->due_date->format('Y-m-d');
    }

    public function cancelEditDueDate(): void
    {
        $this->reset(['editDueDateId', 'editDueDate']);
    }

    public function confirmEditDueDate(): void
    {
        $this->validate(['editDueDate' => 'required|date']);

        $payment = $this->paymentInScope((int) $this->editDueDateId);
        $this->authorizeAbility('expenses.edit', $payment);

        if (!$payment->isPaid()) {
            $payment->changeDueDate(\Carbon\Carbon::parse($this->editDueDate));
            session()->flash('message', __('Due date updated.'));
        }

        $this->cancelEditDueDate();

        // Refresh the viewing expense
        if ($this->viewingExpense) {
            $this->viewingExpense->refresh();
            $this->viewingExpense->load('payments.paidBy');
        }
    }

    public function unmarkExpensePaid(int $expenseId): void
    {
        $expense = $this->expenseInScope($expenseId);
        $this->authorizeAbility('expenses.edit_paid', $expense);

        $expense->unmarkAsPaid();

        session()->flash('message', __('Expense payment reverted to unpaid.'));
    }

    public function unmarkPaymentPaid(int $paymentId): void
    {
        $payment = $this->paymentInScope($paymentId);
        $this->authorizeAbility('expenses.edit_paid', $payment);

        if ($payment->isPaid()) {
            $payment->markAsPending();
            session()->flash('message', __('Payment reverted to pending.'));
        }

        // Refresh the viewing expense
        if ($this->viewingExpense) {
            $this->viewingExpense->refresh();
            $this->viewingExpense->load('payments');
        }
    }

    public function markPaymentAsOverdue(int $paymentId): void
    {
        $payment = $this->paymentInScope($paymentId);
        $this->authorizeAbility('expenses.pay', $payment);

        $payment->markAsOverdue();

        // Refresh the viewing expense
        if ($this->viewingExpense) {
            $this->viewingExpense->refresh();
            $this->viewingExpense->load('payments');
        }
    }

    public function deleteExpense(int $expenseId): void
    {
        $expense = $this->expenseInScope($expenseId);
        $this->authorizeAbility('expenses.delete', $expense);

        // Delete receipt file if exists
        if ($expense->receipt_path) {
            Storage::delete($expense->receipt_path);
        }

        // Delete related items and payments
        $expense->items()->delete();
        $expense->payments()->delete();
        $expense->delete();

        session()->flash('message', __('Expense deleted successfully.'));
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
