<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\ChangeOrder;
use App\Models\JobSite;
use App\Services\CostCodeLedger;
use App\Services\PaymentScheduleService;
use Livewire\Component;

class JobSiteFinancialReport extends Component
{
    use AuthorizesAbility;

    public JobSite $jobSite;

    public function mount(JobSite $jobSite): void
    {
        $this->authorizeAbility('project-report.view', $jobSite);

        $this->jobSite = $jobSite->load(['project']);
    }

    public function getFinancialsProperty(): array
    {
        // Revenue: base (job_amount) + the jobsite-level change orders the
        // client has approved. What is still awaiting a decision is reported
        // separately so the screen can show it without counting it.
        $baseContractValue = $this->jobSite->getContractValue();
        $changeOrdersTotal = $this->jobSite->getApprovedChangeOrdersTotal();
        $pendingChangeOrdersTotal = $this->jobSite->getPendingChangeOrdersTotal();
        $contractValue = $this->jobSite->getAdjustedContractValue();

        // Cost: expenses scoped to this jobsite
        $expenses = $this->jobSite->expenses;
        $totalExpenses = $expenses->sum('total_amount');

        // Cost: contracts scoped to this jobsite, each adjusted for their own change orders
        $contracts = $this->jobSite->contracts()->committed()->with(['changeOrders', 'payments'])->get();
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
            'pending_change_orders_total' => $pendingChangeOrdersTotal,
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
            ->committed()
            ->with([
                'subcontractor:id,name',
                'changeOrders',
                'payments',
            ])
            ->orderBy('contract_number')
            ->get();
    }

    public function getRevenueDetailProperty(): array
    {
        $changeOrders = $this->jobSite->changeOrders()
            ->with('items')
            ->orderBy('requested_date')
            ->get()
            ->map(fn ($co) => [
                'date' => $co->requested_date,
                'title' => $co->title,
                'cost' => (float) $co->cost_impact,
                'status' => $co->status,
                'status_label' => $co->getStatusLabel(),
                'amount' => (float) $co->amount,
            ]);

        // Split, so the breakdown can add up the ones that count and still
        // account for the ones that do not. A change order that vanished from
        // the report would be read as a change order that was lost.
        return [
            'change_orders' => $changeOrders
                ->where('status', ChangeOrder::STATUS_APPROVED)->values()->all(),
            'uncounted_change_orders' => $changeOrders
                ->where('status', '!=', ChangeOrder::STATUS_APPROVED)->values()->all(),
        ];
    }

    public function getPaymentScheduleProperty(): array
    {
        return PaymentScheduleService::forJobSite($this->jobSite)->build();
    }

    public function render()
    {
        $budget = $this->jobSite->budget;
        $ledger = $budget ? CostCodeLedger::for($budget) : null;

        return view('livewire.job-site.job-site-financial-report', [
            'costCodes' => [
                'budgets' => $ledger ? [['budget' => $budget, 'grid' => $ledger->grid()]] : [],
                'totals' => $ledger?->totals(),
            ],
            'financials' => $this->financials,
            'expensesDetail' => $this->expensesDetail,
            'contractsDetail' => $this->contractsDetail,
            'revenueDetail' => $this->revenueDetail,
            'paymentSchedule' => $this->paymentSchedule,
        ])->layout('components.layouts.app');
    }
}
