<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfController extends Controller
{
    public function download(Invoice $invoice)
    {
        $invoice->load(['client', 'project', 'jobSite', 'items', 'createdBy']);

        $company = Company::first();

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
        ]);

        $pdf->setPaper('letter', 'portrait');

        $clientName = $invoice->client->company_name ?? 'client';
        $filename = sprintf('invoice-%s-%s.pdf', $invoice->invoice_number, $clientName);
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $filename);

        return $pdf->download($filename);
    }

    public function stream(Invoice $invoice)
    {
        $invoice->load(['client', 'project', 'jobSite', 'items', 'createdBy']);

        $company = Company::first();

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
        ]);

        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('invoice.pdf');
    }
}
