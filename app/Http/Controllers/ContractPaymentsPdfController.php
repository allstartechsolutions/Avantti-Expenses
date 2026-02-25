<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ContractPaymentsPdfController extends Controller
{
    public function download(Request $request)
    {
        $contracts = $this->getFilteredContracts($request);
        $company = Company::first();
        $summary = $this->getSummary($contracts);

        $pdf = Pdf::loadView('pdf.contract-payments', [
            'contracts' => $contracts,
            'company' => $company,
            'summary' => $summary,
            'filters' => $this->getActiveFilters($request),
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('letter', 'landscape');

        $filename = 'contract-payments-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(Request $request)
    {
        $contracts = $this->getFilteredContracts($request);
        $company = Company::first();
        $summary = $this->getSummary($contracts);

        $pdf = Pdf::loadView('pdf.contract-payments', [
            'contracts' => $contracts,
            'company' => $company,
            'summary' => $summary,
            'filters' => $this->getActiveFilters($request),
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('letter', 'landscape');

        return $pdf->stream('contract-payments.pdf');
    }

    private function getFilteredContracts(Request $request)
    {
        $clientFilter = $request->query('client');
        $projectFilter = $request->query('project');
        $subcontractorFilter = $request->query('subcontractor');
        $projectManagerFilter = $request->query('project_manager');
        $statusFilter = $request->query('status');
        $showZeroBalance = $request->boolean('show_zero_balance');

        return Contract::with(['project.client', 'jobSite', 'subcontractor', 'latestPayment', 'changeOrders'])
            ->withSum('payments as total_paid_cents', 'amount')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->when($clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $clientFilter)))
            ->when($projectFilter, fn ($q) => $q->where('project_id', $projectFilter))
            ->when($subcontractorFilter, fn ($q) => $q->where('subcontractor_id', $subcontractorFilter))
            ->when($projectManagerFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('project_manager_id', $projectManagerFilter)))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->unless($showZeroBalance, fn ($q) => $q->whereNotIn('status', ['paid', 'cancelled']))
            ->orderBy('project_id')
            ->orderBy('job_site_id')
            ->get();
    }

    private function getSummary($contracts): array
    {
        $totalValue = 0;
        $totalPaid = 0;
        $totalChangeOrders = 0;
        $totalBalance = 0;

        foreach ($contracts as $contract) {
            $paid = ($contract->total_paid_cents ?? 0) / 100;
            $co = ($contract->change_orders_total_cents ?? 0) / 100;
            $totalValue += $contract->amount;
            $totalPaid += $paid;
            $totalChangeOrders += $co;
            $totalBalance += round($contract->amount + $co - $paid, 2);
        }

        return [
            'count' => $contracts->count(),
            'total_value' => $totalValue,
            'total_change_orders' => $totalChangeOrders,
            'total_paid' => $totalPaid,
            'total_balance' => $totalBalance,
        ];
    }

    private function getActiveFilters(Request $request): array
    {
        $filters = [];

        if ($request->query('client')) {
            $client = \App\Models\Client::find($request->query('client'));
            if ($client) {
                $filters[] = __('Client') . ': ' . $client->company_name;
            }
        }
        if ($request->query('project')) {
            $project = \App\Models\Project::find($request->query('project'));
            if ($project) {
                $filters[] = __('Project') . ': ' . $project->project_name;
            }
        }
        if ($request->query('subcontractor')) {
            $sub = \App\Models\Subcontractor::find($request->query('subcontractor'));
            if ($sub) {
                $filters[] = __('Subcontractor') . ': ' . $sub->company_name;
            }
        }
        if ($request->query('status')) {
            $filters[] = __('Status') . ': ' . ucfirst(str_replace('_', ' ', $request->query('status')));
        }

        return $filters;
    }
}
