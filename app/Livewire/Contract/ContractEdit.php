<?php

namespace App\Livewire\Contract;

use App\Livewire\Concerns\ManagesContractAllocations;
use App\Models\Contract;
use App\Models\Subcontractor;
use App\Models\SubcontractorEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContractEdit extends Component
{
    use ManagesContractAllocations, WithFileUploads;

    public Contract $contract;

    // Form fields
    public $subcontractor_id = null;
    public $subcontractorSearch = '';
    public $subcontractor_employee_id = null;
    public $job_site_id = null;
    public $start_date;
    public $end_date = '';
    public $amount = '';
    public $notes = '';
    public $contract_file = null;
    public $existingFilePath = null;
    public $removeFile = false;

    public function mount(Contract $contract)
    {
        $this->contract = $contract->load(['project', 'jobSite', 'subcontractor']);

        $this->subcontractor_id = $contract->subcontractor_id;
        $this->subcontractorSearch = $contract->subcontractor?->company_name ?? '';
        $this->subcontractor_employee_id = $contract->subcontractor_employee_id;
        $this->job_site_id = $contract->job_site_id;
        $this->start_date = $contract->start_date->format('Y-m-d');
        $this->end_date = $contract->end_date?->format('Y-m-d') ?? '';
        $this->amount = $contract->amount;
        $this->notes = $contract->notes ?? '';
        $this->existingFilePath = $contract->contract_file_path;

        $this->allocations = $contract->allocations()
            ->with('budgetItem')
            ->get()
            ->map(fn ($allocation) => [
                'budget_item_id' => $allocation->budget_item_id,
                'code_display' => $allocation->cost_code_display,
                'amount' => $allocation->amount,
            ])
            ->all();
    }

    protected function allocationProjectId(): int
    {
        return $this->contract->project_id;
    }

    public function selectSubcontractor($id)
    {
        $subcontractor = Subcontractor::find($id);
        if ($subcontractor) {
            $this->subcontractor_id = $id;
            $this->subcontractorSearch = $subcontractor->company_name;
            $this->subcontractor_employee_id = null;
        }
    }

    public function clearSubcontractor()
    {
        $this->subcontractor_id = null;
        $this->subcontractorSearch = '';
        $this->subcontractor_employee_id = null;
    }

    public function removeExistingFile()
    {
        $this->removeFile = true;
        $this->existingFilePath = null;
    }

    public function save()
    {
        $this->validate([
            'subcontractor_id' => 'nullable|exists:vendors,id,is_subcontractor,1',
            'subcontractor_employee_id' => ['nullable', Rule::exists('subcontractor_employees', 'id')->where('subcontractor_id', $this->subcontractor_id)],
            'job_site_id' => 'nullable|exists:job_sites,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'amount' => 'required|numeric|min:0',
            'contract_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if (! $this->allocationsValid()) {
            return;
        }

        // Handle file
        $filePath = $this->contract->contract_file_path;

        if ($this->contract_file) {
            // New file uploaded — delete old if exists
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }
            $filePath = $this->contract_file->store('contracts', 'local');
        } elseif ($this->removeFile) {
            // User removed the existing file
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }
            $filePath = null;
        }

        DB::transaction(function () use ($filePath) {
            $this->contract->update([
                'subcontractor_id' => $this->subcontractor_id ?: null,
                'subcontractor_employee_id' => $this->subcontractor_employee_id ?: null,
                'job_site_id' => $this->job_site_id ?: null,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date ?: null,
                'amount' => $this->amount,
                'notes' => $this->notes ?: null,
                'contract_file_path' => $filePath,
            ]);

            $this->syncAllocations($this->contract);
        });

        session()->flash('message', 'Contract updated successfully!');

        return redirect()->route('contracts.show', $this->contract->id);
    }

    public function render()
    {
        $subcontractors = collect();
        if ($this->subcontractorSearch && strlen($this->subcontractorSearch) >= 2 && !$this->subcontractor_id) {
            $subcontractors = Subcontractor::where('name', 'like', '%' . $this->subcontractorSearch . '%')
                ->take(10)
                ->get();
        }

        $jobSites = $this->contract->project->jobSites()->orderBy('job_site_name')->get();

        $employees = $this->subcontractor_id
            ? SubcontractorEmployee::where('subcontractor_id', $this->subcontractor_id)->orderBy('name')->get()
            : collect();

        $allocationBudget = $this->allocationBudget();

        return view('livewire.contract.contract-edit', [
            'subcontractors' => $subcontractors,
            'jobSites' => $jobSites,
            'employees' => $employees,
            'allocationBudget' => $allocationBudget,
            'allocationItems' => $this->allocationSearchResults(),
            'allocationDefaultItem' => $allocationBudget?->defaultItem(),
        ])->layout('components.layouts.app');
    }
}
