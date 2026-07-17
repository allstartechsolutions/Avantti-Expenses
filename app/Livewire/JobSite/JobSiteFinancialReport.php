<?php

namespace App\Livewire\JobSite;

use App\Models\JobSite;
use App\Services\PaymentScheduleService;
use Livewire\Component;

class JobSiteFinancialReport extends Component
{
    public JobSite $jobSite;

    public function mount(JobSite $jobSite): void
    {
        $this->jobSite = $jobSite->load(['project']);
    }

    public function getFinancialsProperty(): array
    {
        // Revenue: base (job_amount) + jobsite-level change orders
        $baseContractValue = (float) $this->jobSite->job_amount;
        $changeOrdersTotal = round($this->jobSite->changeOrders()->sum('amount') / 100, 2);
        $contractValue = round($baseContractValue + $changeOrdersTotal, 2);

        // Cost: expenses scoped to this jobsite
        $expenses = $this->jobSite->expenses;
        $totalExpenses = $expenses->sum('total_amount');

        // Cost: contracts scoped to this jobsite, each adjusted for their own change orders
        $contracts = $this->jobSite->contracts()->with(['changeOrders', 'payments'])->get();
        $contractsAdjusted = 0;
        $contractsPaid = 0;
        foreach ($contracts as $contract) {
            $contractsAdjusted += $contract->getAdjustedAmount();
            $contractsPaid += $contract->getAmountPaid();
        }
        $contractsUnpaid = round($contractsAdjusted - $contractsPaid, 2);

        $profit = round($contractValue - $totalExpenses - $contractsAdjusted, 2);

        return [
            'base_contract_value' => $baseContractValue,
            'change_orders_total' => $changeOrdersTotal,
            'contract_value' => $contractValue,
            'total_expenses' => $totalExpenses,
            'expenses_count' => $expenses->count(),
            'contracts_adjusted' => $contractsAdjusted,
            'contracts_paid' => $contractsPaid,
            'contracts_unpaid' => $contractsUnpaid,
            'contracts_count' => $contracts->count(),
            'profit' => $profit,
        ];
    }

    public function getExpensesDetailProperty()
    {
        return $this->jobSite->expenses()
            ->with('supplier:id,name')
            ->orderByDesc('expense_date')
            ->get();
    }

    public function getContractsDetailProperty()
    {
        return $this->jobSite->contracts()
            ->with([
                'subcontractor:id,company_name',
                'changeOrders',
                'payments',
            ])
            ->orderBy('contract_number')
            ->get();
    }

    public function getRevenueDetailProperty(): array
    {
        $changeOrders = $this->jobSite->changeOrders()
            ->orderBy('requested_date')
            ->get()
            ->map(fn ($co) => [
                'date' => $co->requested_date,
                'title' => $co->title,
                'amount' => (float) $co->amount,
            ])
            ->all();

        return [
            'change_orders' => $changeOrders,
        ];
    }

    public function getPaymentScheduleProperty(): array
    {
        return PaymentScheduleService::forJobSite($this->jobSite)->build();
    }

    public function render()
    {
        return view('livewire.job-site.job-site-financial-report', [
            'financials' => $this->financials,
            'expensesDetail' => $this->expensesDetail,
            'contractsDetail' => $this->contractsDetail,
            'revenueDetail' => $this->revenueDetail,
            'paymentSchedule' => $this->paymentSchedule,
        ])->layout('components.layouts.app');
    }
}
