<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Quotation;
use App\Models\QuotationVendor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * The scope PDF that goes out with an RFQ. Also the fallback when the install
 * has no mail set up: procurement downloads it and sends it themselves.
 */
class QuotationRfqPdfController extends Controller
{
    public function download(Request $request, Quotation $quotation)
    {
        return $this->build($request, $quotation)->download($this->filename($quotation));
    }

    public function stream(Request $request, Quotation $quotation)
    {
        return $this->build($request, $quotation)->stream($this->filename($quotation));
    }

    protected function build(Request $request, Quotation $quotation)
    {
        $quotation->load(['items', 'jobSite', 'project']);

        // Optional: the vendor the copy is addressed to. Anyone else's row is
        // ignored rather than shown.
        $quotationVendor = null;
        if ($vendorRowId = (int) $request->query('vendor')) {
            $quotationVendor = QuotationVendor::with('vendor')
                ->where('quotation_id', $quotation->id)
                ->find($vendorRowId);
        }

        $company = Company::first();

        $pdf = Pdf::loadView('pdf.quotation-rfq', [
            'quotation' => $quotation,
            'quotationVendor' => $quotationVendor,
            'vendor' => $quotationVendor?->vendor,
            'company' => $company,
            'deliveryLocation' => $quotation->jobSite?->job_site_name
                ?? $quotation->project?->project_name
                ?? '—',
            'replyTo' => $company?->email,
        ]);

        $pdf->setPaper('letter', 'portrait');

        return $pdf;
    }

    protected function filename(Quotation $quotation): string
    {
        $number = $quotation->quotation_number ?: $quotation->id;

        return preg_replace('/[^a-zA-Z0-9\-_.]/', '-', "cotacao-{$number}.pdf");
    }
}
