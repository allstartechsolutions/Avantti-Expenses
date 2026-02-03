<?php

namespace App\Livewire\JobSite;

use App\Models\JobSite;
use Livewire\Component;

class JobSiteOverview extends Component
{
    public JobSite $jobSite;

    public function mount(JobSite $jobSite): void
    {
        $this->jobSite = $jobSite->load(['project', 'createdBy']);
    }

    public function render()
    {
        $changeOrders = $this->jobSite->changeOrders()->get();
        $totalChangeOrdersAmount = $changeOrders->sum('amount');

        $expenses = $this->jobSite->expenses()->get();
        $totalExpensesAmount = $expenses->sum('total_amount');

        return view('livewire.job-site.job-site-overview', [
            'changeOrders' => $changeOrders,
            'totalChangeOrdersAmount' => $totalChangeOrdersAmount,
            'expenses' => $expenses,
            'totalExpensesAmount' => $totalExpensesAmount,
        ])->layout('components.layouts.app');
    }
}
