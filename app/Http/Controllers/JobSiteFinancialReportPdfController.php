<?php

namespace App\Http\Controllers;

use App\Models\ChangeOrder;
use App\Models\Company;
use App\Models\JobSite;
use App\Services\CostCodeLedger;
use App\Services\PaymentScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class JobSiteFinancialReportPdfController extends Controller
{
    public function download(JobSite $jobSite)
    {
        $data = $this->buildPdfData($jobSite);
        $pdf = Pdf::loadView('pdf.job-site-financial-report', $data);
        $pdf->setPaper('letter', 'portrait');

        $filename = 'jobsite-' . Str::slug($jobSite->job_site_name) . '-financial-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(JobSite $jobSite)
    {
        $data = $this->buildPdfData($jobSite);
        $pdf = Pdf::loadView('pdf.job-site-financial-report', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('jobsite-financial-report.pdf');
    }

    private function buildPdfData(JobSite $jobSite): array
    {
        $jobSite->load(['project.client', 'supervisor']);

        // Approved change orders only — the same rule as the screen.
        $baseContractValue = $jobSite->getContractValue();
        $changeOrdersTotal = $jobSite->getApprovedChangeOrdersTotal();
        $pendingChangeOrdersTotal = $jobSite->getPendingChangeOrdersTotal();
        $contractValue = $jobSite->getAdjustedContractValue();

        $expensesCollection = $jobSite->expenses()
            ->with('supplier:id,name')
            ->orderByDesc('expense_date')
            ->get();
        $totalExpenses = $expensesCollection->sum('total_amount');

        $contractsCollection = $jobSite->contracts()
            ->committed()
            ->with(['subcontractor:id,name', 'changeOrders', 'payments'])
            ->orderBy('contract_number')
            ->get();

        $contractsAdjusted = 0;
        $contractsPaid = 0;
        foreach ($contractsCollection as $contract) {
            $contractsAdjusted += $contract->getAdjustedAmount();
            $contractsPaid += $contract->getAmountPaid();
        }
        $contractsUnpaid = round($contractsAdjusted - $contractsPaid, 2);

        $profit = round($contractValue - $totalExpenses - $contractsAdjusted, 2);

        $changeOrderRows = $jobSite->changeOrders()
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

        // Split, so the breakdown adds up the ones that count and still
        // accounts for the ones that do not.
        $changeOrders = $changeOrderRows
            ->where('status', ChangeOrder::STATUS_APPROVED)->values()->all();
        $uncountedChangeOrders = $changeOrderRows
            ->where('status', '!=', ChangeOrder::STATUS_APPROVED)->values()->all();

        $ledger = $jobSite->budget ? CostCodeLedger::for($jobSite->budget) : null;

        return [
            'jobSite' => $jobSite,
            'company' => Company::first(),
            'financials' => [
                'base_contract_value' => $baseContractValue,
                'change_orders_total' => $changeOrdersTotal,
                'pending_change_orders_total' => $pendingChangeOrdersTotal,
                'contract_value' => $contractValue,
                'total_expenses' => $totalExpenses,
                'expenses_count' => $expensesCollection->count(),
                'contracts_adjusted' => $contractsAdjusted,
                'contracts_paid' => $contractsPaid,
                'contracts_unpaid' => $contractsUnpaid,
                'contracts_count' => $contractsCollection->count(),
                'profit' => $profit,
            ],
            'changeOrders' => $changeOrders,
            'uncountedChangeOrders' => $uncountedChangeOrders,
            'expenses' => $expensesCollection,
            'contracts' => $contractsCollection,
            'costCodes' => [
                'budgets' => $ledger ? [['budget' => $jobSite->budget, 'grid' => $ledger->grid()]] : [],
                'totals' => $ledger?->totals(),
            ],
            'paymentSchedule' => PaymentScheduleService::forJobSite($jobSite)->build(),
            'generatedAt' => now(),
        ];
    }
}
