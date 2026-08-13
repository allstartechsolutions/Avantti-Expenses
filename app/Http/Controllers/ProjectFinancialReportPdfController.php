<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Project;
use App\Services\PaymentScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ProjectFinancialReportPdfController extends Controller
{
    public function download(Project $project)
    {
        $data = $this->buildPdfData($project);
        $pdf = Pdf::loadView('pdf.project-financial-report', $data);
        $pdf->setPaper('letter', 'portrait');

        $filename = 'project-' . Str::slug($project->project_name) . '-financial-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(Project $project)
    {
        $data = $this->buildPdfData($project);
        $pdf = Pdf::loadView('pdf.project-financial-report', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('project-financial-report.pdf');
    }

    private function buildPdfData(Project $project): array
    {
        $project->load([
            'client',
            'projectManager',
            'jobSites',
        ]);

        $contractValue = $project->getAdjustedContractValue();
        $baseContractValue = $project->getContractValue();
        $changeOrdersTotal = round($contractValue - $baseContractValue, 2);

        $expensesCollection = $project->expenses()
            ->with(['supplier:id,name', 'jobSite:id,job_site_name'])
            ->orderByDesc('expense_date')
            ->get();
        $totalExpenses = $expensesCollection->sum('total_amount');

        $contractsCollection = $project->contracts()
            ->with([
                'subcontractor:id,name',
                'jobSite:id,job_site_name',
                'changeOrders',
                'payments',
            ])
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

        $financials = [
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
        ];

        $breakdown = $this->buildBreakdown($project);
        $revenueDetail = $this->buildRevenueDetail($project);

        return [
            'project' => $project,
            'company' => Company::first(),
            'financials' => $financials,
            'breakdown' => $breakdown,
            'revenueDetail' => $revenueDetail,
            'expenses' => $expensesCollection,
            'contracts' => $contractsCollection,
            'paymentSchedule' => PaymentScheduleService::forProject($project)->build(),
            'generatedAt' => now(),
        ];
    }

    private function buildBreakdown(Project $project): array
    {
        $rows = [];

        $projectLevelCOTotal = $project->projectLevelChangeOrders()->sum('amount');
        $projectLevelExpenses = $project->projectLevelExpenses->sum('total_amount');

        $projectLevelContracts = $project->projectLevelContracts()->with(['changeOrders', 'payments'])->get();
        $projectLevelContractsAdjusted = 0;
        $projectLevelContractsPaid = 0;
        foreach ($projectLevelContracts as $contract) {
            $projectLevelContractsAdjusted += $contract->getAdjustedAmount();
            $projectLevelContractsPaid += $contract->getAmountPaid();
        }

        $projectLevelBase = $project->amount_source?->value === 'manual'
            ? (float) $project->initial_amount
            : 0.0;
        $projectLevelContractValue = round($projectLevelBase + ($projectLevelCOTotal / 100), 2);

        $rows[] = [
            'name' => __('Project-level'),
            'contract_value' => $projectLevelContractValue,
            'expenses' => $projectLevelExpenses,
            'contracts_adjusted' => $projectLevelContractsAdjusted,
            'contracts_paid' => $projectLevelContractsPaid,
            'contracts_unpaid' => round($projectLevelContractsAdjusted - $projectLevelContractsPaid, 2),
            'profit' => round($projectLevelContractValue - $projectLevelExpenses - $projectLevelContractsAdjusted, 2),
        ];

        foreach ($project->jobSites as $jobSite) {
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

            $rows[] = [
                'name' => $jobSite->job_site_name,
                'contract_value' => $jsContractValue,
                'expenses' => $jsExpenses,
                'contracts_adjusted' => $jsContractsAdjusted,
                'contracts_paid' => $jsContractsPaid,
                'contracts_unpaid' => round($jsContractsAdjusted - $jsContractsPaid, 2),
                'profit' => round($jsContractValue - $jsExpenses - $jsContractsAdjusted, 2),
            ];
        }

        return $rows;
    }

    private function buildRevenueDetail(Project $project): array
    {
        $baseLines = [];

        if ($project->amount_source?->value === 'manual') {
            $baseLines[] = [
                'label' => __('Initial Amount'),
                'amount' => (float) $project->initial_amount,
            ];
        } else {
            foreach ($project->jobSites as $jobSite) {
                $baseLines[] = [
                    'label' => $jobSite->job_site_name,
                    'amount' => (float) $jobSite->job_amount,
                ];
            }
        }

        $changeOrders = $project->changeOrders()
            ->with('jobSite:id,job_site_name')
            ->orderBy('requested_date')
            ->get()
            ->map(fn ($co) => [
                'date' => $co->requested_date,
                'title' => $co->title,
                'scope' => $co->jobSite?->job_site_name ?? __('Project-level'),
                'amount' => (float) $co->amount,
            ])
            ->all();

        return [
            'base_lines' => $baseLines,
            'change_orders' => $changeOrders,
        ];
    }
}
