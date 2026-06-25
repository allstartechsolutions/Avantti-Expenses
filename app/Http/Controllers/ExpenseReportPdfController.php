<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\ExpenseReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpenseReportPdfController extends Controller
{
    public function download(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.expense-report', $data);
        $pdf->setPaper('letter', $data['view'] === 'detail' ? 'landscape' : 'portrait');

        $filename = 'expense-report-' . $data['view'] . '-' . $data['fromDate'] . '-to-' . $data['toDate'] . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.expense-report', $data);
        $pdf->setPaper('letter', $data['view'] === 'detail' ? 'landscape' : 'portrait');

        return $pdf->stream('expense-report.pdf');
    }

    private function buildPdfData(Request $request): array
    {
        $fromDate = $request->query('fromDate') ?: Carbon::now()->startOfYear()->toDateString();
        $toDate = $request->query('toDate') ?: Carbon::now()->endOfYear()->toDateString();
        $clientFilter = $request->query('clientFilter') ?: '';
        $projectFilter = $request->query('projectFilter') ?: '';
        $jobSiteFilter = $request->query('jobSiteFilter') ?: '';
        $vendorFilter = $request->query('vendorFilter') ?: '';
        $categoryFilter = $request->query('categoryFilter') ?: '';
        $statusFilter = $request->query('statusFilter') ?: 'all';

        $view = in_array($request->query('view'), ['project', 'vendor', 'costcode', 'detail'], true)
            ? $request->query('view')
            : 'project';

        $service = new ExpenseReportService(
            $fromDate,
            $toDate,
            $projectFilter,
            $jobSiteFilter,
            $vendorFilter,
            $categoryFilter,
            $clientFilter,
            $statusFilter,
        );

        return [
            'view' => $view,
            'kpis' => $service->kpis(),
            'byProject' => $service->byProject(),
            'byVendor' => $service->byVendor(),
            'byCostCode' => $service->byCostCode(),
            'detail' => $service->detail(),
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'statusFilter' => $statusFilter,
            'categoryFilter' => $categoryFilter,
            'project' => $projectFilter ? Project::find($projectFilter) : null,
            'jobSite' => $jobSiteFilter ? JobSite::find($jobSiteFilter) : null,
            'client' => $clientFilter ? Client::find($clientFilter) : null,
            'vendor' => $vendorFilter ? Supplier::find($vendorFilter) : null,
            'company' => Company::first(),
            'generatedAt' => now(),
        ];
    }
}
