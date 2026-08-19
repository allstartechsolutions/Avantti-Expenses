<?php

namespace App\Livewire\Dashboard;

use App\Enums\ProjectStatus;
use App\Models\ContractPayment;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\ModuleAccess;
use App\Models\PaymentBatch;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Livewire\Component;

class DashboardIndex extends Component
{
    public string $month;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function getModulesProperty(): array
    {
        return [
            'invoices' => ModuleAccess::isEnabled('invoices'),
            'estimates' => ModuleAccess::isEnabled('estimates'),
            'projects' => ModuleAccess::isEnabled('projects'),
        ];
    }

    protected function monthRange(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return [$start, $end];
    }

    public function getAvailableMonthsProperty(): array
    {
        $months = [];
        $cursor = now()->startOfMonth()->subMonths(11);
        $end = now()->startOfMonth()->addMonths(2);

        while ($cursor <= $end) {
            $months[$cursor->format('Y-m')] = $cursor->translatedFormat('F Y');
            $cursor->addMonth();
        }

        return $months;
    }

    public function getKpisProperty(): array
    {
        [$start, $end] = $this->monthRange();
        $modules = $this->modules;

        $cashToPayInstallments = ExpensePayment::where('status', '!=', 'paid')
            ->whereBetween('due_date', [$start, $end])
            ->whereHas('expense', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->sum('amount');

        $cashToPayOneTime = Expense::where('status', 'unpaid')
            ->where('total_installments', 1)
            ->whereBetween('payment_due_date', [$start, $end])
            ->sum('total_amount');

        $contractBalances = 0;
        $unpaidContracts = \App\Models\Contract::committed()->where('status', '!=', 'paid')->get();
        foreach ($unpaidContracts as $contract) {
            $contractBalances += max(0, $contract->getBalanceDue());
        }

        $cashToPay = ($cashToPayInstallments / 100) + ($cashToPayOneTime / 100) + $contractBalances;

        $receivables = 0;
        $pastDueInvoices = 0;
        if ($modules['invoices']) {
            $invoiceQuery = Invoice::whereIn('status', ['sent', 'pending', 'partial'])
                ->whereBetween('due_date', [$start, $end])
                ->get();
            foreach ($invoiceQuery as $invoice) {
                $receivables += max(0, $invoice->getBalanceDue());
            }

            $pastDueInvoices = Invoice::whereIn('status', ['sent', 'pending', 'partial'])
                ->where('due_date', '<', now()->toDateString())
                ->count();
        }

        $openEstimatesValue = 0;
        $openEstimatesCount = 0;
        if ($modules['estimates']) {
            $openEstimates = Estimate::whereIn('status', ['sent', 'pending'])->get();
            $openEstimatesCount = $openEstimates->count();
            foreach ($openEstimates as $estimate) {
                $openEstimatesValue += (float) $estimate->total_amount;
            }
        }

        $activeProjects = Project::where('status', ProjectStatus::IN_PROGRESS)->count();

        $atRiskFromInvoices = $modules['invoices']
            ? Invoice::whereIn('status', ['sent', 'pending', 'partial'])
                ->where('due_date', '<', now()->toDateString())
                ->pluck('project_id')
                ->filter()
                ->unique()
            : collect();

        $atRiskFromExpensePayments = ExpensePayment::where('status', 'overdue')
            ->with('expense:id,project_id')
            ->get()
            ->pluck('expense.project_id')
            ->filter()
            ->unique();

        $atRiskProjects = $atRiskFromInvoices
            ->merge($atRiskFromExpensePayments)
            ->unique()
            ->count();

        $projectsOverBudget = Project::where('status', ProjectStatus::IN_PROGRESS)
            ->withSum('expenses as expenses_total', 'total_amount')
            ->get()
            ->filter(function ($p) {
                $contractValue = $p->getAdjustedContractValue();
                if ($contractValue <= 0) {
                    return false;
                }
                $expensesDollars = round(($p->expenses_total ?? 0) / 100, 2);
                return $expensesDollars > $contractValue;
            })
            ->count();

        $openPurchaseOrders = PurchaseOrder::where('status', 'pending')->count();

        return [
            'cash_to_pay' => $cashToPay,
            'receivables' => $receivables,
            'past_due_invoices' => $pastDueInvoices,
            'open_estimates' => $openEstimatesValue,
            'open_estimates_count' => $openEstimatesCount,
            'active_projects' => $activeProjects,
            'at_risk_projects' => $atRiskProjects,
            'projects_over_budget' => $projectsOverBudget,
            'open_purchase_orders' => $openPurchaseOrders,
        ];
    }

    public function getOverduePaymentsProperty()
    {
        return ExpensePayment::where('status', 'overdue')
            ->with(['expense.project:id,project_name', 'expense.supplier:id,name'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();
    }

    public function getPastDueInvoicesListProperty()
    {
        if (! $this->modules['invoices']) {
            return collect();
        }

        return Invoice::with(['client:id,company_name', 'project:id,project_name'])
            ->whereIn('status', ['sent', 'pending', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(10)
            ->get();
    }

    public function getOverBudgetProjectsProperty()
    {
        return Project::where('status', ProjectStatus::IN_PROGRESS)
            ->withSum('expenses as expenses_total', 'total_amount')
            ->get()
            ->filter(function ($p) {
                $contractValue = $p->getAdjustedContractValue();
                if ($contractValue <= 0) {
                    return false;
                }
                $expensesDollars = round(($p->expenses_total ?? 0) / 100, 2);
                return $expensesDollars > $contractValue;
            })
            ->sortByDesc(function ($p) {
                $expensesDollars = round(($p->expenses_total ?? 0) / 100, 2);
                return $expensesDollars - $p->getAdjustedContractValue();
            })
            ->take(10);
    }

    public function getPendingApprovalsProperty(): array
    {
        $pos = PurchaseOrder::with(['project:id,project_name', 'supplier:id,name'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $batches = PaymentBatch::withCount('items')
            ->where('status', 'draft')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'purchase_orders' => $pos,
            'payment_batches' => $batches,
        ];
    }

    public function getCashflowChartProperty(): array
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->startOfMonth()->subMonths($i));
        }

        $labels = $months->map(fn ($m) => $m->translatedFormat('M Y'))->all();
        $outflow = [];
        $inflow = [];
        $invoicesEnabled = $this->modules['invoices'];

        foreach ($months as $monthStart) {
            $monthEnd = (clone $monthStart)->endOfMonth();

            $expensePayments = ExpensePayment::where('status', 'paid')
                ->whereBetween('paid_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $contractPayments = ContractPayment::whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $outflow[] = round(($expensePayments + $contractPayments) / 100, 2);

            if ($invoicesEnabled) {
                $invoicePayments = InvoicePayment::where('status', 'completed')
                    ->whereBetween('payment_date', [$monthStart, $monthEnd])
                    ->sum('amount');

                $inflow[] = round($invoicePayments / 100, 2);
            }
        }

        return [
            'labels' => $labels,
            'outflow' => $outflow,
            'inflow' => $inflow,
            'show_inflow' => $invoicesEnabled,
        ];
    }

    public function render()
    {
        $role = auth()->user()->role?->name ?? 'employee';

        return view('livewire.dashboard.dashboard-index', [
            'role' => $role,
            'modules' => $this->modules,
        ])->layout('components.layouts.app');
    }
}
