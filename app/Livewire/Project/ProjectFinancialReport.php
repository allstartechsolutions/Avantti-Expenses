<?php

namespace App\Livewire\Project;

use App\Models\Project;
use App\Services\PaymentScheduleService;
use Livewire\Component;

class ProjectFinancialReport extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project->load([
            'client',
            'projectManager',
            'jobSites',
        ]);
    }

    public function getFinancialsProperty(): array
    {
        // Revenue: base contract value + all change orders (signed, project + jobsite level)
        $contractValue = $this->project->getAdjustedContractValue();
        $baseContractValue = $this->project->getContractValue();
        $changeOrdersTotal = round($contractValue - $baseContractValue, 2);

        // Cost: expenses (includes jobsite-level and PO-converted; sum via accessor returns dollars)
        $expenses = $this->project->expenses;
        $totalExpenses = $expenses->sum('total_amount');

        // Cost: contracts adjusted (each Contract::getAdjustedAmount already includes ContractChangeOrders)
        $contracts = $this->project->contracts()->with(['changeOrders', 'payments'])->get();
        $contractsAdjusted = 0;
        $contractsPaid = 0;
        foreach ($contracts as $contract) {
            $contractsAdjusted += $contract->getAdjustedAmount();
            $contractsPaid += $contract->getAmountPaid();
        }
        $contractsUnpaid = round($contractsAdjusted - $contractsPaid, 2);

        // Profit
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

    public function getBreakdownProperty(): array
    {
        $rows = [];

        // Project-level row (resources with job_site_id IS NULL)
        $projectLevelChangeOrders = $this->project->projectLevelChangeOrders()->sum('amount');
        $projectLevelExpenses = $this->project->projectLevelExpenses->sum('total_amount');

        $projectLevelContracts = $this->project->projectLevelContracts()->with(['changeOrders', 'payments'])->get();
        $projectLevelContractsAdjusted = 0;
        $projectLevelContractsPaid = 0;
        foreach ($projectLevelContracts as $contract) {
            $projectLevelContractsAdjusted += $contract->getAdjustedAmount();
            $projectLevelContractsPaid += $contract->getAmountPaid();
        }

        // In manual mode, project base value is initial_amount; in from_jobsites mode it's 0
        // (the value lives on the jobsites). Either way, project-level COs are added here.
        $projectLevelBase = $this->project->amount_source?->value === 'manual'
            ? (float) $this->project->initial_amount
            : 0.0;
        $projectLevelContractValue = round($projectLevelBase + ($projectLevelChangeOrders / 100), 2);
        $projectLevelContractsUnpaid = round($projectLevelContractsAdjusted - $projectLevelContractsPaid, 2);

        $rows[] = [
            'name' => __('Project-level'),
            'contract_value' => $projectLevelContractValue,
            'expenses' => $projectLevelExpenses,
            'contracts_adjusted' => $projectLevelContractsAdjusted,
            'contracts_paid' => $projectLevelContractsPaid,
            'contracts_unpaid' => $projectLevelContractsUnpaid,
            'profit' => round($projectLevelContractValue - $projectLevelExpenses - $projectLevelContractsAdjusted, 2),
            'url' => null,
        ];

        // One row per jobsite
        foreach ($this->project->jobSites as $jobSite) {
            $jsCoTotal = $jobSite->changeOrders()->sum('amount');
            $jsContractValue = round((float) $jobSite->job_amount + ($jsCoTotal / 100), 2);

            $jsExpenses = $jobSite->expenses->sum('total_amount');

            $jsContracts = $jobSite->contracts()->with(['changeOrders', 'payments'])->get();
            $jsContractsAdjusted = 0;
            $jsContractsPaid = 0;
            foreach ($jsContracts as $contract) {
                $jsContractsAdjusted += $contract->getAdjustedAmount();
                $jsContractsPaid += $contract->getAmountPaid();
            }
            $jsContractsUnpaid = round($jsContractsAdjusted - $jsContractsPaid, 2);

            $rows[] = [
                'name' => $jobSite->job_site_name,
                'contract_value' => $jsContractValue,
                'expenses' => $jsExpenses,
                'contracts_adjusted' => $jsContractsAdjusted,
                'contracts_paid' => $jsContractsPaid,
                'contracts_unpaid' => $jsContractsUnpaid,
                'profit' => round($jsContractValue - $jsExpenses - $jsContractsAdjusted, 2),
                'url' => route('jobsites.overview', $jobSite),
            ];
        }

        return $rows;
    }

    public function getExpensesDetailProperty()
    {
        return $this->project->expenses()
            ->with(['supplier:id,name', 'jobSite:id,job_site_name'])
            ->orderByDesc('expense_date')
            ->get();
    }

    public function getContractsDetailProperty()
    {
        return $this->project->contracts()
            ->with([
                'subcontractor:id,company_name',
                'jobSite:id,job_site_name',
                'changeOrders',
                'payments',
            ])
            ->orderBy('contract_number')
            ->get();
    }

    public function getRevenueDetailProperty(): array
    {
        $baseLines = [];

        if ($this->project->amount_source?->value === 'manual') {
            $baseLines[] = [
                'label' => __('Initial Amount'),
                'amount' => (float) $this->project->initial_amount,
            ];
        } else {
            foreach ($this->project->jobSites as $jobSite) {
                $baseLines[] = [
                    'label' => $jobSite->job_site_name,
                    'amount' => (float) $jobSite->job_amount,
                ];
            }
        }

        $changeOrders = $this->project->changeOrders()
            ->with('jobSite:id,job_site_name')
            ->orderBy('requested_date')
            ->get()
            ->map(function ($co) {
                return [
                    'date' => $co->requested_date,
                    'title' => $co->title,
                    'scope' => $co->jobSite?->job_site_name ?? __('Project-level'),
                    'amount' => (float) $co->amount,
                ];
            })
            ->all();

        return [
            'base_lines' => $baseLines,
            'change_orders' => $changeOrders,
        ];
    }

    public function getPaymentScheduleProperty(): array
    {
        return PaymentScheduleService::forProject($this->project)->build();
    }

    public function render()
    {
        return view('livewire.project.project-financial-report', [
            'financials' => $this->financials,
            'breakdown' => $this->breakdown,
            'revenueDetail' => $this->revenueDetail,
            'expensesDetail' => $this->expensesDetail,
            'contractsDetail' => $this->contractsDetail,
            'paymentSchedule' => $this->paymentSchedule,
        ])->layout('components.layouts.app');
    }
}
