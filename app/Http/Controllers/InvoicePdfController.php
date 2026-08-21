<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\PermissionResolver;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfController extends Controller
{

    /**
     * P22: this was behind `auth` and nothing else, so anybody signed in could
     * fetch any client's document by changing the number in the URL.
     */
    private function authorizeRecord(): void
    {
        abort_unless(
            app(PermissionResolver::class)->allows(auth()->user(), 'invoices.view'),
            403,
            __('You do not have permission to do that.'),
        );
    }
    public function download(Invoice $invoice)
    {
        $this->authorizeRecord();

        $invoice->load(['client', 'project', 'jobSite', 'items', 'createdBy', 'payments']);

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
        $this->authorizeRecord();

        $invoice->load(['client', 'project', 'jobSite', 'items', 'createdBy', 'payments']);

        $company = Company::first();

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
        ]);

        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('invoice.pdf');
    }
}
