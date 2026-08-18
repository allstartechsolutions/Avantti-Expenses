<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ContractMeasurement;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Boletim de medição — the document that gets printed and signed, so it
 * carries the measured percentages per cost code, the retention snapshot
 * taken at approval, and signature lines for both parties.
 */
class ContractMeasurementPdfController extends Controller
{
    public function download(ContractMeasurement $measurement)
    {
        $pdf = Pdf::loadView('pdf.contract-measurement', $this->data($measurement));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download($this->filename($measurement));
    }

    public function stream(ContractMeasurement $measurement)
    {
        $pdf = Pdf::loadView('pdf.contract-measurement', $this->data($measurement));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream($this->filename($measurement));
    }

    private function data(ContractMeasurement $measurement): array
    {
        $measurement->load([
            'items.budgetItem',
            'contract.project',
            'contract.jobSite',
            'contract.subcontractor',
            'contract.changeOrders',
            'scheduleItem',
            'createdBy',
            'approvedBy',
            'payments',
        ]);

        return [
            'measurement' => $measurement,
            'contract' => $measurement->contract,
            'company' => Company::first(),
            'generatedAt' => now(),
        ];
    }

    private function filename(ContractMeasurement $measurement): string
    {
        return sprintf(
            'boletim-medicao-%s-%02d.pdf',
            $measurement->contract?->contract_number ?? 'contract',
            $measurement->measurement_number
        );
    }
}
