<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\Supplier;
use App\Services\PaymentDetailReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentDetailReportPdfController extends Controller
{
    public function download(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.payment-detail-report', $data);
        $pdf->setPaper('letter', $data['view'] === 'detail' ? 'landscape' : 'portrait');

        $filename = 'payment-details-' . $data['view'] . '-' . $data['fromDate'] . '-to-' . $data['toDate'] . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.payment-detail-report', $data);
        $pdf->setPaper('letter', $data['view'] === 'detail' ? 'landscape' : 'portrait');

        return $pdf->stream('payment-details.pdf');
    }

    private function buildPdfData(Request $request): array
    {
        $fromDate = $request->query('fromDate') ?: Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->query('toDate') ?: Carbon::now()->endOfMonth()->toDateString();
        $clientFilter = $request->query('clientFilter') ?: '';
        $projectFilter = $request->query('projectFilter') ?: '';
        $jobSiteFilter = $request->query('jobSiteFilter') ?: '';
        $vendorFilter = $request->query('vendorFilter') ?: '';
        $subcontractorFilter = $request->query('subcontractorFilter') ?: '';
        // Multi-select: array from the report page (statusFilter[0]=paid...),
        // but old single-string links still work; invalid values drop out.
        $statusFilter = array_values(array_intersect(
            (array) ($request->query('statusFilter') ?: []),
            ['paid', 'pending', 'overdue']
        ));
        $typeFilter = in_array($request->query('typeFilter'), ['all', 'expenses', 'contracts'], true)
            ? $request->query('typeFilter')
            : 'all';

        $view = in_array($request->query('view'), ['detail', 'project', 'vendor'], true)
            ? $request->query('view')
            : 'detail';

        $service = new PaymentDetailReportService(
            $fromDate,
            $toDate,
            $projectFilter,
            $jobSiteFilter,
            $vendorFilter,
            $subcontractorFilter,
            $clientFilter,
            $statusFilter,
            $typeFilter,
        );

        return [
            'view' => $view,
            'kpis' => $service->kpis(),
            'rows' => $service->rows(),
            'byProject' => $service->byProject(),
            'byVendor' => $service->byVendor(),
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'statusFilter' => $statusFilter === []
                ? __('All')
                : implode(', ', array_map('ucfirst', $statusFilter)),
            'typeFilter' => $typeFilter,
            'client' => $clientFilter ? Client::find($clientFilter) : null,
            'project' => $projectFilter ? Project::find($projectFilter) : null,
            'jobSite' => $jobSiteFilter ? JobSite::find($jobSiteFilter) : null,
            'vendor' => $vendorFilter ? Supplier::find($vendorFilter) : null,
            'subcontractor' => $subcontractorFilter ? Subcontractor::find($subcontractorFilter) : null,
            'company' => Company::first(),
            'generatedAt' => now(),
        ];
    }
}
