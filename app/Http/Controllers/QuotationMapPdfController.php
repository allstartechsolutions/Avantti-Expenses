<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Quotation;
use App\Services\QuotationComparisonService;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The comparison map as a PDF — the sheet that gets filed with the award, so
 * the choice can be defended long after the round is closed.
 */
class QuotationMapPdfController extends Controller
{
    public function __construct(protected QuotationComparisonService $comparison) {}

    public function download(Quotation $quotation)
    {
        return $this->build($quotation)->download($this->filename($quotation));
    }

    public function stream(Quotation $quotation)
    {
        return $this->build($quotation)->stream($this->filename($quotation));
    }

    protected function build(Quotation $quotation)
    {
        $quotation->load(['project', 'jobSite', 'requisition', 'budgetItem']);

        $pdf = Pdf::loadView('pdf.quotation-map', [
            'comparison' => $this->comparison->build($quotation),
            'quotation' => $quotation,
            'company' => Company::first(),
        ]);

        // A map with several columns needs the width.
        $pdf->setPaper('letter', 'landscape');

        return $pdf;
    }

    protected function filename(Quotation $quotation): string
    {
        $number = $quotation->quotation_number ?: $quotation->id;

        return preg_replace('/[^a-zA-Z0-9\-_.]/', '-', "mapa-cotacao-{$number}.pdf");
    }
}
