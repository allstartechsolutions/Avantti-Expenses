<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\PaymentScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaymentScheduleReportPdfController extends Controller
{
    public function download(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.payment-schedule-report', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('payment-schedule-' . now()->format('Y-m-d') . '.pdf');
    }

    public function stream(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.payment-schedule-report', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('payment-schedule-report.pdf');
    }

    private function buildPdfData(Request $request): array
    {
        $clientFilter = (int) $request->query('clientFilter') ?: null;
        $projectFilter = (int) $request->query('projectFilter') ?: null;
        $jobSiteFilter = (int) $request->query('jobSiteFilter') ?: null;

        return [
            'paymentSchedule' => PaymentScheduleService::forSystem($clientFilter, $projectFilter, $jobSiteFilter)->build(),
            'client' => $clientFilter ? Client::find($clientFilter) : null,
            'project' => $projectFilter ? Project::find($projectFilter) : null,
            'jobSite' => $jobSiteFilter ? JobSite::find($jobSiteFilter) : null,
            'company' => Company::first(),
            'generatedAt' => now(),
        ];
    }
}
