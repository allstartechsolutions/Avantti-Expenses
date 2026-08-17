<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Services\AccountsPayableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountsPayableReportPdfController extends Controller
{
    public function download(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.accounts-payable-report', $data);
        $pdf->setPaper('letter', 'portrait');

        $filename = 'accounts-payable-' . $data['fromDate'] . '-to-' . $data['toDate'] . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.accounts-payable-report', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('accounts-payable.pdf');
    }

    /**
     * The PDF reads the same AccountsPayableService as the screen, so the
     * two can never drift (it used to re-implement the queries, which left
     * contract payments out of the PDF's rows and Paid in Period).
     */
    private function buildPdfData(Request $request): array
    {
        $fromDate = $request->query('fromDate') ?: Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->query('toDate') ?: Carbon::now()->endOfMonth()->toDateString();
        $projectFilter = $request->query('projectFilter') ?: '';
        $clientFilter = $request->query('clientFilter') ?: '';
        $statusFilter = $request->query('statusFilter') ?: 'unpaid';

        $service = new AccountsPayableService($fromDate, $toDate, $projectFilter, $statusFilter, $clientFilter);

        return [
            'rows' => $service->rows(),
            'kpis' => $service->kpis(),
            'projections' => $service->projections(),
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'statusFilter' => $statusFilter,
            'project' => $projectFilter ? Project::find($projectFilter) : null,
            'client' => $clientFilter ? Client::find($clientFilter) : null,
            'company' => Company::first(),
            'generatedAt' => now(),
        ];
    }
}
