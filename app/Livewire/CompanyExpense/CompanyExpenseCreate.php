<?php

namespace App\Livewire\CompanyExpense;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesExpenseForm;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * File a company (general) expense — one that belongs to no project and is
 * categorised, with its account code, rather than cost-coded.
 *
 * The same form as ExpenseCreate (ManagesExpenseForm), pointed at no project:
 * the Location picker becomes a Category picker and the lines carry no cost
 * code. There is no destination to check, so the one guard is the company
 * grant. docs/company-expenses.md
 */
class CompanyExpenseCreate extends Component
{
    use WithFileUploads, ManagesExpenseForm, AuthorizesAbility;

    public function mount(): void
    {
        $this->authorizeAbility('company-expenses.create');

        $this->startBlankExpenseForm();
    }

    protected function expenseProjectId(): ?int
    {
        return null;
    }

    public function save()
    {
        $this->validateExpenseForm();

        $this->authorizeAbility('company-expenses.create');

        $receiptPath = null;

        if ($this->expense_receipt) {
            $receiptPath = $this->expense_receipt->store('expenses', 'local');
        }

        DB::transaction(function () use ($receiptPath) {
            $expense = Expense::create($this->expenseHeaderData() + [
                'receipt_path' => $receiptPath,
                'created_by' => Auth::id(),
            ]);

            $this->syncExpenseItems($expense);

            if ($this->expense_has_installments) {
                $expense->generatePaymentSchedule();
            }
        });

        session()->flash('message', __('Company expense created successfully!'));

        return redirect()->route('company-expenses.index');
    }

    public function render()
    {
        return view('livewire.company-expense.company-expense-create', [
            'suppliers' => $this->supplierSearchResults(),
            'budgetItems' => collect(),
            'catalogItems' => $this->catalogItemSearchResults(),
            'jobSites' => collect(),
            'categories' => $this->selectableCategories(),
        ])->layout('components.layouts.app');
    }
}
