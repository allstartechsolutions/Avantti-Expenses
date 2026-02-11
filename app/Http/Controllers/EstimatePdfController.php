<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Estimate;
use Barryvdh\DomPDF\Facade\Pdf;

class EstimatePdfController extends Controller
{
    public function download(Estimate $estimate)
    {
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
