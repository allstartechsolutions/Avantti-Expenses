<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\CompanyFinancialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CompanyFinancialReportPdfController extends Controller
{
    public function download(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.company-financial-report', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('company-financials-'.now()->format('Y-m-d').'.pdf');
    }

    public function stream(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.company-financial-report', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('company-financials.pdf');
    }

    /**
     * Reads the same service and the same detail filters as the screen, so
     * the PDF always shows what the user is looking at.
     */
    private function buildPdfData(Request $request): array
    {
        $clientFilter = (int) $request->query('clientFilter') ?: null;
        $projectFilter = (int) $request->query('projectFilter') ?: null;
        $jobSiteFilter = (int) $request->query('jobSiteFilter') ?: null;
        $fromDate = $request->query('fromDate') ?: null;
        $toDate = $request->query('toDate') ?: null;
        $directionFilter = $request->query('directionFilter') ?: '';
        $statusFilter = $request->query('statusFilter') ?: '';
        $sourceFilter = $request->query('sourceFilter') ?: '';

        $data = CompanyFinancialService::forFilters($clientFilter, $projectFilter, $jobSiteFilter)
            ->between($fromDate, $toDate)
            ->build();

        $rows = $data['items']
            ->when($directionFilter !== '', fn ($items) => $items->where('direction', $directionFilter))
            ->when($statusFilter !== '', fn ($items) => $items->where('status', $statusFilter))
            ->when($sourceFilter !== '', fn ($items) => $items->where('source', $sourceFilter))
            ->values();

        return [
            'data' => $data,
            'rows' => $rows,
            'client' => $clientFilter ? Client::find($clientFilter) : null,
            'project' => $projectFilter ? Project::find($projectFilter) : null,
            'jobSite' => $jobSiteFilter ? JobSite::find($jobSiteFilter) : null,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'company' => Company::first(),
            'generatedAt' => now(),
        ];
    }
}
