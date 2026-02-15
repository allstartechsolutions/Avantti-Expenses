<?php

namespace App\Livewire\Contract;

use App\Models\Contract;
use App\Models\Subcontractor;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContractEdit extends Component
{
    use WithFileUploads;

    public Contract $contract;

    // Form fields
    public $subcontractor_id = null;
    public $subcontractorSearch = '';
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
        $this->job_site_id = $contract->job_site_id;
        $this->start_date = $contract->start_date->format('Y-m-d');
        $this->end_date = $contract->end_date?->format('Y-m-d') ?? '';
        $this->amount = $contract->amount;
        $this->notes = $contract->notes ?? '';
        $this->existingFilePath = $contract->contract_file_path;
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

    public function removeExistingFile()
    {
        $this->removeFile = true;
        $this->existingFilePath = null;
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

        $this->contract->update([
            'subcontractor_id' => $this->subcontractor_id ?: null,
            'job_site_id' => $this->job_site_id ?: null,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date ?: null,
            'amount' => $this->amount,
            'notes' => $this->notes ?: null,
            'contract_file_path' => $filePath,
        ]);

        session()->flash('message', 'Contract updated successfully!');

        return redirect()->route('contracts.show', $this->contract->id);
    }

    public function render()
    {
        $subcontractors = collect();
        if ($this->subcontractorSearch && strlen($this->subcontractorSearch) >= 2 && !$this->subcontractor_id) {
            $subcontractors = Subcontractor::where('company_name', 'like', '%' . $this->subcontractorSearch . '%')
                ->take(10)
                ->get();
        }

        $jobSites = $this->contract->project->jobSites()->orderBy('job_site_name')->get();

        return view('livewire.contract.contract-edit', [
            'subcontractors' => $subcontractors,
            'jobSites' => $jobSites,
        ])->layout('components.layouts.app');
    }
}
