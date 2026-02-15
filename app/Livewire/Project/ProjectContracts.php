<?php

namespace App\Livewire\Project;

use App\Models\Project;
use Livewire\Component;

class ProjectContracts extends Component
{
    public Project $project;

    public $search = '';
    public $locationFilter = 'all';
    public $statusFilter = 'all';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        $query = $this->project->contracts()
            ->with(['jobSite', 'subcontractor', 'createdBy']);

        // Location filter
        if ($this->locationFilter === 'project') {
            $query->whereNull('job_site_id');
        } elseif ($this->locationFilter !== 'all' && is_numeric($this->locationFilter)) {
            $query->where('job_site_id', $this->locationFilter);
        }

        // Status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('contract_number', 'like', '%' . $this->search . '%')
                    ->orWhere('notes', 'like', '%' . $this->search . '%')
                    ->orWhereHas('subcontractor', function ($sq) {
                        $sq->where('company_name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        $contracts = $query->orderBy('start_date', 'desc')->get();

        $totalContractsAmount = $contracts->sum('amount');
        $activeCount = $contracts->where('status', 'active')->count();
        $completedCount = $contracts->where('status', 'completed')->count();
        $paidCount = $contracts->where('status', 'paid')->count();

        return view('livewire.project.project-contracts', [
            'contracts' => $contracts,
            'jobSites' => $jobSites,
            'totalContractsAmount' => $totalContractsAmount,
            'activeCount' => $activeCount,
            'completedCount' => $completedCount,
            'paidCount' => $paidCount,
        ])->layout('components.layouts.app');
    }
}
