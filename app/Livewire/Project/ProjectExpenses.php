<?php

namespace App\Livewire\Project;

use App\Models\Expense;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ProjectExpenses extends Component
{
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

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function openExpenseViewModal(int $expenseId): void
    {
        $expense = Expense::with(['payments', 'items.budgetItem', 'items.catalogItem', 'supplier', 'jobSite'])
            ->findOrFail($expenseId);

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

        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-view-modal');
    }

    public function closeExpenseModal(): void
    {
        $this->showExpenseModal = false;
        $this->viewingExpense = null;
        $this->dispatch('close-modal', 'expense-view-modal');
    }

    public function markExpenseAsPaid(int $expenseId): void
    {
        $expense = Expense::findOrFail($expenseId);

        if ($expense->isOneTime() && $expense->status !== 'paid') {
            $expense->update([
                'status' => 'paid',
                'paid_date' => now(),
            ]);
            session()->flash('message', 'Expense marked as paid.');
        }
    }

    public function markPaymentAsPaid(int $paymentId): void
    {
        $payment = \App\Models\ExpensePayment::findOrFail($paymentId);
        $payment->update([
            'status' => 'paid',
            'paid_date' => now(),
        ]);

        // Refresh the viewing expense
        if ($this->viewingExpense) {
            $this->viewingExpense->refresh();
            $this->viewingExpense->load('payments');
            $this->viewingExpense->updateStatusFromPayments();
        }

        session()->flash('message', 'Payment marked as paid.');
    }

    public function markPaymentAsOverdue(int $paymentId): void
    {
        $payment = \App\Models\ExpensePayment::findOrFail($paymentId);
        $payment->update(['status' => 'overdue']);

        // Refresh the viewing expense
        if ($this->viewingExpense) {
            $this->viewingExpense->refresh();
            $this->viewingExpense->load('payments');
            $this->viewingExpense->updateStatusFromPayments();
        }
    }

    public function deleteExpense(int $expenseId): void
    {
        $expense = Expense::findOrFail($expenseId);

        // Delete receipt file if exists
        if ($expense->receipt_path) {
            Storage::delete($expense->receipt_path);
        }

        // Delete related items and payments
        $expense->items()->delete();
        $expense->payments()->delete();
        $expense->delete();

        session()->flash('message', 'Expense deleted successfully.');
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

        return view('livewire.project.project-expenses', [
            'expenses' => $expenses,
            'jobSites' => $jobSites,
            'totalExpensesAmount' => $totalExpensesAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalPendingAmount' => $totalPendingAmount,
        ])->layout('components.layouts.app');
    }
}
