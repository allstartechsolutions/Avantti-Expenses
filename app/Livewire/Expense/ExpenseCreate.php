<?php

namespace App\Livewire\Expense;

use App\Livewire\Concerns\AuthorizesAbility;
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
    use WithFileUploads, ManagesExpenseForm, AuthorizesAbility;

    public Project $project;

    public ?JobSite $jobSite = null;

    public function mount(?Project $project = null, ?JobSite $jobSite = null)
    {
        // If coming from job site route, get project from job site.
        // `exists` and not truthiness: an unfilled route parameter can arrive
        // as a blank model, which is truthy and has no project behind it.
        if ($jobSite?->exists) {
            $this->jobSite = $jobSite;
            $this->project = $jobSite->project;
            $this->expense_job_site_id = $jobSite->id;
        } elseif ($project?->exists) {
            $this->project = $project;
        } else {
            abort(404, 'Project or Job Site required');
        }

        // Answered against the job site where there is one: a site membership
        // overrides the project's, in both directions.
        $this->authorizeAbility('expenses.create', $this->expenseScope());

        $this->startBlankExpenseForm();
    }

    protected function expenseProjectId(): int
    {
        return $this->project->id;
    }

    /** The record this expense is being keyed in against. */
    protected function expenseScope(): JobSite|Project
    {
        return $this->jobSite ?? $this->project;
    }

    public function save()
    {
        $this->validateExpenseForm();

        // Both ends: the screen it was opened from, and wherever the Location
        // picker is now pointing.
        $this->authorizeAbility('expenses.create', $this->expenseScope());
        $this->authorizeAbility('expenses.create', $this->expenseDestination());

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
            'jobSites' => $this->selectableJobSites('expenses.create'),
        ])->layout('components.layouts.app');
    }
}
