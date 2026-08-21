<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Estimate;
use App\Services\PermissionResolver;
use Barryvdh\DomPDF\Facade\Pdf;

class EstimatePdfController extends Controller
{

    /**
     * P22: this was behind `auth` and nothing else, so anybody signed in could
     * fetch any client's document by changing the number in the URL.
     */
    private function authorizeRecord(): void
    {
        abort_unless(
            app(PermissionResolver::class)->allows(auth()->user(), 'estimates.view'),
            403,
            __('You do not have permission to do that.'),
        );
    }
    public function download(Estimate $estimate)
    {
        $this->authorizeRecord();

        $estimate->load(['client', 'project', 'jobSite', 'items', 'createdBy']);

        $company = Company::first();

        $pdf = Pdf::loadView('pdf.estimate', [
            'estimate' => $estimate,
            'company' => $company,
        ]);

        $pdf->setPaper('letter', 'portrait');

        $clientName = $estimate->client->company_name ?? 'client';
        $filename = sprintf('estimate-%s-%s.pdf', $estimate->estimate_number, $clientName);
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $filename);

        return $pdf->download($filename);
    }

    public function stream(Estimate $estimate)
    {
        $this->authorizeRecord();

        $estimate->load(['client', 'project', 'jobSite', 'items', 'createdBy']);

        $company = Company::first();

        $pdf = Pdf::loadView('pdf.estimate', [
            'estimate' => $estimate,
            'company' => $company,
        ]);

        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('estimate.pdf');
    }
}
