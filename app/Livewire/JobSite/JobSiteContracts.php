<?php

namespace App\Livewire\JobSite;

use App\Models\JobSite;
use Livewire\Component;

class JobSiteContracts extends Component
{
    public JobSite $jobSite;

    public $search = '';
    public $statusFilter = 'all';

    public function mount(JobSite $jobSite): void
    {
        $this->jobSite = $jobSite->load('project');
    }

    public function render()
    {
        $query = $this->jobSite->contracts()
            ->with(['subcontractor', 'createdBy']);

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
                        $sq->where('name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        $contracts = $query->orderBy('start_date', 'desc')->get();

        $totalContractsAmount = $contracts->sum('amount');
        $activeCount = $contracts->where('status', 'active')->count();
        $completedCount = $contracts->where('status', 'completed')->count();
        $paidCount = $contracts->where('status', 'paid')->count();

        return view('livewire.job-site.job-site-contracts', [
            'contracts' => $contracts,
            'totalContractsAmount' => $totalContractsAmount,
            'activeCount' => $activeCount,
            'completedCount' => $completedCount,
            'paidCount' => $paidCount,
        ])->layout('components.layouts.app');
    }
}
