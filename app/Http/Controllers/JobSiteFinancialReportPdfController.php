<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobSite;
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

        $baseContractValue = (float) $jobSite->job_amount;
        $changeOrdersTotal = round($jobSite->changeOrders()->sum('amount') / 100, 2);
        $contractValue = round($baseContractValue + $changeOrdersTotal, 2);

        $expensesCollection = $jobSite->expenses()
            ->with('supplier:id,name')
            ->orderByDesc('expense_date')
            ->get();
        $totalExpenses = $expensesCollection->sum('total_amount');

        $contractsCollection = $jobSite->contracts()
            ->with(['subcontractor:id,company_name', 'changeOrders', 'payments'])
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

        $changeOrders = $jobSite->changeOrders()
            ->orderBy('requested_date')
            ->get()
            ->map(fn ($co) => [
                'date' => $co->requested_date,
                'title' => $co->title,
                'amount' => (float) $co->amount,
            ])
            ->all();

        return [
            'jobSite' => $jobSite,
            'company' => Company::first(),
            'financials' => [
                'base_contract_value' => $baseContractValue,
                'change_orders_total' => $changeOrdersTotal,
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
            'expenses' => $expensesCollection,
            'contracts' => $contractsCollection,
            'generatedAt' => now(),
        ];
    }
}
