<?php

namespace App\Livewire\Contract;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesContractAllocations;
use App\Models\Contract;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\SubcontractorEmployee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContractCreate extends Component
{
    use AuthorizesAbility;

    use ManagesContractAllocations, WithFileUploads;

    // Context
    public Project $project;
    public ?JobSite $jobSite = null;

    // Form fields
    public $subcontractor_id = null;
    public $subcontractorSearch = '';
    public $subcontractor_employee_id = null;
    public $job_site_id = null;
    public $start_date;
    public $end_date = '';
    public $amount = '';
    public $retention_percent = '';
    public $notes = '';
    public $contract_file = null;

    public function mount(?Project $project = null, ?JobSite $jobSite = null)
    {
        // `exists` and not truthiness: an unfilled route parameter can arrive
        // as a blank model, which is truthy and has no project behind it.
        if ($jobSite?->exists) {
            $this->jobSite = $jobSite;
            $this->project = $jobSite->project;
            $this->job_site_id = $jobSite->id;
        } elseif ($project?->exists) {
            $this->project = $project;
        } else {
            abort(404, 'Project or Job Site required');
        }

        $this->authorizeAbility('contracts.create', $this->contractScope());

        $this->start_date = now()->format('Y-m-d');
    }

    /** The record this contract is being raised against. */
    protected function contractScope(): JobSite|Project
    {
        return $this->jobSite ?? $this->project;
    }

    protected function allocationProjectId(): int
    {
        return $this->project->id;
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

    public function save()
    {
        $this->authorizeAbility('contracts.create', $this->contractScope());

        $this->validate([
            'subcontractor_id' => 'nullable|exists:vendors,id,is_subcontractor,1',
            'subcontractor_employee_id' => ['nullable', Rule::exists('subcontractor_employees', 'id')->where('subcontractor_id', $this->subcontractor_id)],
            'job_site_id' => 'nullable|exists:job_sites,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'amount' => 'required|numeric|min:0',
            'retention_percent' => 'nullable|numeric|min:0|max:50',
            'contract_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'retention_percent.max' => __('Retention cannot exceed 50%.'),
        ]);

        if (! $this->allocationsValid()) {
            return;
        }

        $filePath = null;
        if ($this->contract_file) {
            $filePath = $this->contract_file->store('contracts', 'local');
        }

        $contract = DB::transaction(function () use ($filePath) {
            $contract = Contract::create([
                'project_id' => $this->project->id,
                'job_site_id' => $this->job_site_id ?: null,
                'subcontractor_id' => $this->subcontractor_id ?: null,
                'subcontractor_employee_id' => $this->subcontractor_employee_id ?: null,
                'contract_number' => Contract::generateContractNumber(),
                'status' => 'active',
                'start_date' => $this->start_date,
                'end_date' => $this->end_date ?: null,
                'amount' => $this->amount,
                'retention_percent' => $this->retention_percent === '' || $this->retention_percent === null ? null : $this->retention_percent,
                'notes' => $this->notes ?: null,
                'contract_file_path' => $filePath,
                'created_by' => Auth::id(),
            ]);

            $this->syncAllocations($contract);

            $contract->recordStatusChange(Auth::user(), null, 'active');

            return $contract;
        });

        session()->flash('message', __('Contract created successfully!'));

        if ($this->jobSite) {
            return redirect()->route('jobsites.contracts', $this->jobSite);
        }

        return redirect()->route('projects.contracts', $this->project);
    }

    public function render()
    {
        $subcontractors = collect();
        if ($this->subcontractorSearch && strlen($this->subcontractorSearch) >= 2 && !$this->subcontractor_id) {
            $subcontractors = Subcontractor::where('name', 'like', '%' . $this->subcontractorSearch . '%')
                ->take(10)
                ->get();
        }

        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        $employees = $this->subcontractor_id
            ? SubcontractorEmployee::where('subcontractor_id', $this->subcontractor_id)->orderBy('name')->get()
            : collect();

        $allocationBudget = $this->allocationBudget();

        return view('livewire.contract.contract-create', [
            'subcontractors' => $subcontractors,
            'jobSites' => $jobSites,
            'employees' => $employees,
            'allocationBudget' => $allocationBudget,
            'allocationItems' => $this->allocationSearchResults(),
            'allocationDefaultItem' => $allocationBudget?->defaultItem(),
        ])->layout('components.layouts.app');
    }
}
