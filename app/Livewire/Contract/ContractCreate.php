<?php

namespace App\Livewire\Contract;

use App\Models\Contract;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Subcontractor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContractCreate extends Component
{
    use WithFileUploads;

    // Context
    public Project $project;
    public ?JobSite $jobSite = null;

    // Form fields
    public $subcontractor_id = null;
    public $subcontractorSearch = '';
    public $job_site_id = null;
    public $start_date;
    public $end_date = '';
    public $amount = '';
    public $notes = '';
    public $contract_file = null;

    public function mount(?Project $project = null, ?JobSite $jobSite = null)
    {
        if ($jobSite) {
            $this->jobSite = $jobSite;
            $this->project = $jobSite->project;
            $this->job_site_id = $jobSite->id;
        } elseif ($project) {
            $this->project = $project;
        } else {
            abort(404, 'Project or Job Site required');
        }

        $this->start_date = now()->format('Y-m-d');
    }

    public function selectSubcontractor($id)
    {
        $subcontractor = Subcontractor::find($id);
        if ($subcontractor) {
            $this->subcontractor_id = $id;
            $this->subcontractorSearch = $subcontractor->company_name;
        }
    }

    public function clearSubcontractor()
    {
        $this->subcontractor_id = null;
        $this->subcontractorSearch = '';
    }

    public function save()
    {
        $this->validate([
            'subcontractor_id' => 'nullable|exists:subcontractors,id',
            'job_site_id' => 'nullable|exists:job_sites,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'amount' => 'required|numeric|min:0',
            'contract_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $filePath = null;
        if ($this->contract_file) {
            $filePath = $this->contract_file->store('contracts', 'local');
        }

        $contract = Contract::create([
            'project_id' => $this->project->id,
            'job_site_id' => $this->job_site_id ?: null,
            'subcontractor_id' => $this->subcontractor_id ?: null,
            'contract_number' => Contract::generateContractNumber(),
            'status' => 'active',
            'start_date' => $this->start_date,
            'end_date' => $this->end_date ?: null,
            'amount' => $this->amount,
            'notes' => $this->notes ?: null,
            'contract_file_path' => $filePath,
            'created_by' => Auth::id(),
        ]);

        $contract->recordStatusChange(Auth::user(), null, 'active');

        session()->flash('message', 'Contract created successfully!');

        if ($this->jobSite) {
            return redirect()->route('jobsites.contracts', $this->jobSite);
        }

        return redirect()->route('projects.contracts', $this->project);
    }

    public function render()
    {
        $subcontractors = collect();
        if ($this->subcontractorSearch && strlen($this->subcontractorSearch) >= 2 && !$this->subcontractor_id) {
            $subcontractors = Subcontractor::where('company_name', 'like', '%' . $this->subcontractorSearch . '%')
                ->take(10)
                ->get();
        }

        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        return view('livewire.contract.contract-create', [
            'subcontractors' => $subcontractors,
            'jobSites' => $jobSites,
        ])->layout('components.layouts.app');
    }
}
