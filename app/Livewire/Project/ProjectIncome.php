<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Income;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProjectIncome extends Component
{
    use AuthorizesAdmin, WithFileUploads;

    public Project $project;

    // Filters
    public $incomeSearch = '';
    public $incomeLocationFilter = 'all';

    // Form modal (create/edit)
    public $editingIncomeId = null;
    public $income_date = '';
    public $income_job_site_id = '';
    public $income_title = '';
    public $income_description = '';
    public $income_amount = null;
    public $income_uploads = [];

    // View modal
    public $viewingIncome = null;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function openAddModal(): void
    {
        $this->resetForm();
        $this->income_date = now()->format('Y-m-d');
        $this->dispatch('open-modal', 'income-form-modal');
    }

    public function openEditModal(int $incomeId): void
    {
        if ($this->viewingIncome) {
            $this->closeViewModal();
        }

        $income = $this->project->income()->findOrFail($incomeId);

        $this->editingIncomeId = $income->id;
        $this->income_date = $income->income_date->format('Y-m-d');
        $this->income_job_site_id = $income->job_site_id ?? '';
        $this->income_title = $income->title;
        $this->income_description = $income->description ?? '';
        $this->income_amount = $income->amount;

        $this->dispatch('open-modal', 'income-form-modal');
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', 'income-form-modal');
    }

    public function saveIncome(): void
    {
        $validated = $this->validate([
            'income_date' => 'required|date',
            'income_title' => 'required|string|max:255',
            'income_description' => 'nullable|string',
            'income_amount' => 'required|numeric|min:0.01|max:99999999',
            'income_job_site_id' => 'nullable|exists:job_sites,id',
            'income_uploads.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [], [
            'income_date' => __('date'),
            'income_title' => __('title'),
            'income_description' => __('description'),
            'income_amount' => __('amount'),
            'income_job_site_id' => __('location'),
            'income_uploads.*' => __('file'),
        ]);

        $jobSiteId = $this->income_job_site_id ?: null;

        if ($jobSiteId && !$this->project->jobSites()->whereKey($jobSiteId)->exists()) {
            $this->addError('income_job_site_id', __('The selected location is invalid.'));
            return;
        }

        $data = [
            'income_date' => $validated['income_date'],
            'job_site_id' => $jobSiteId,
            'title' => $validated['income_title'],
            'description' => $validated['income_description'] ?: null,
            'amount' => $validated['income_amount'],
        ];

        if ($this->editingIncomeId) {
            $income = $this->project->income()->findOrFail($this->editingIncomeId);
            $income->update($data);
            session()->flash('message', __('Income updated successfully.'));
        } else {
            $income = $this->project->income()->create($data + ['created_by' => auth()->id()]);
            session()->flash('message', __('Income added successfully.'));
        }

        foreach ($this->income_uploads as $upload) {
            $path = $upload->store('income', 'local');

            $income->attachments()->create([
                'file_path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $this->closeFormModal();
    }

    public function openViewModal(int $incomeId): void
    {
        $this->viewingIncome = $this->project->income()
            ->with(['jobSite', 'createdBy'])
            ->findOrFail($incomeId);

        $this->dispatch('open-modal', 'income-view-modal');
    }

    public function closeViewModal(): void
    {
        $this->viewingIncome = null;
        $this->dispatch('close-modal', 'income-view-modal');
    }

    public function deleteIncome(int $incomeId): void
    {
        $this->authorizeAdmin();

        $income = $this->project->income()->findOrFail($incomeId);
        $income->delete();

        if ($this->viewingIncome && $this->viewingIncome->id === $incomeId) {
            $this->closeViewModal();
        }

        session()->flash('message', __('Income deleted successfully.'));
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingIncomeId',
            'income_date',
            'income_job_site_id',
            'income_title',
            'income_description',
            'income_amount',
            'income_uploads',
        ]);
        $this->resetErrorBag();
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        $incomeQuery = $this->project->income()
            ->with(['jobSite', 'createdBy'])
            ->withCount('attachments');

        // Apply location filter
        if ($this->incomeLocationFilter === 'project') {
            $incomeQuery->whereNull('job_site_id');
        } elseif ($this->incomeLocationFilter !== 'all' && is_numeric($this->incomeLocationFilter)) {
            $incomeQuery->where('job_site_id', $this->incomeLocationFilter);
        }

        // Apply search filter
        if ($this->incomeSearch) {
            $incomeQuery->where(function ($query) {
                $query->where('title', 'like', '%' . $this->incomeSearch . '%')
                    ->orWhere('description', 'like', '%' . $this->incomeSearch . '%');
            });
        }

        $incomeRecords = $incomeQuery->orderBy('income_date', 'desc')->get();
        $totalIncomeAmount = $incomeRecords->sum('amount');
        $thisMonthAmount = $incomeRecords
            ->filter(fn ($income) => $income->income_date->isSameMonth(now()))
            ->sum('amount');

        return view('livewire.project.project-income', [
            'incomeRecords' => $incomeRecords,
            'jobSites' => $jobSites,
            'totalIncomeAmount' => $totalIncomeAmount,
            'thisMonthAmount' => $thisMonthAmount,
        ])->layout('components.layouts.app');
    }
}
