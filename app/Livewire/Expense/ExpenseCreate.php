<?php

namespace App\Livewire\Expense;

use App\Livewire\Concerns\ManagesExpenseForm;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class ExpenseCreate extends Component
{
    use WithFileUploads, ManagesExpenseForm;

    public Project $project;

    public ?JobSite $jobSite = null;

    public function mount(?Project $project = null, ?JobSite $jobSite = null)
    {
        // If coming from job site route, get project from job site
        if ($jobSite) {
            $this->jobSite = $jobSite;
            $this->project = $jobSite->project;
            $this->expense_job_site_id = $jobSite->id;
        } elseif ($project) {
            $this->project = $project;
        } else {
            abort(404, 'Project or Job Site required');
        }

        $this->startBlankExpenseForm();
    }

    protected function expenseProjectId(): int
    {
        return $this->project->id;
    }

    public function save()
    {
        $this->validateExpenseForm();

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

        session()->flash('message', __('Expense created successfully!'));

        if ($this->jobSite) {
            return redirect()->route('jobsites.show', ['jobSite' => $this->jobSite->id, 'tab' => 'expenses']);
        }

        return redirect()->route('projects.expenses', $this->project->id);
    }

    public function render()
    {
        return view('livewire.expense.expense-create', [
            'suppliers' => $this->supplierSearchResults(),
            'budgetItems' => $this->budgetItemSearchResults(),
            'catalogItems' => $this->catalogItemSearchResults(),
            'jobSites' => $this->project->jobSites()->orderBy('job_site_name')->get(),
        ])->layout('components.layouts.app');
    }
}
