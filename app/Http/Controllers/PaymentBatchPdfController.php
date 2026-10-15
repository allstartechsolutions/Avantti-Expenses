<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PaymentBatch;
use App\Services\PaymentBatchLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * One payment batch on paper — the header, every line with what it pays,
 * and the approved / pending / rejected totals. Grouped and sorted as the
 * batch screen was when the link was clicked (?group=&sort=&dir=). Guarded
 * on the route with the same grant as the batch screen.
 */
class PaymentBatchPdfController extends Controller
{
    public function download(Request $request, PaymentBatch $paymentBatch)
    {
        return $this->pdf($request, $paymentBatch)->download($this->filename($paymentBatch));
    }

    public function stream(Request $request, PaymentBatch $paymentBatch)
    {
        return $this->pdf($request, $paymentBatch)->stream($this->filename($paymentBatch));
    }

    private function pdf(Request $request, PaymentBatch $paymentBatch)
    {
        $paymentBatch->load(['client', 'project', 'subcontractor', 'projectManager', 'supervisor', 'createdBy', 'approvedBy']);

        $groupBy = PaymentBatchLayout::cleanGroup($request->query('group'));

        $groups = PaymentBatchLayout::lines(
            $paymentBatch,
            $groupBy,
            PaymentBatchLayout::cleanLineSort($request->query('sort')),
            PaymentBatchLayout::cleanDirection($request->query('dir')),
        );
        $items = $groups->flatMap(fn ($group) => $group['rows']);

        $pdf = Pdf::loadView('pdf.payment-batch', [
            'batch' => $paymentBatch,
            'items' => $items,
            'groups' => $groups,
            'groupLabel' => $groupBy ? PaymentBatchLayout::groupOptions()[$groupBy] : null,
            'summary' => PaymentBatchLayout::lineTotals($items),
            'company' => Company::first(),
            'generatedAt' => now(),
        ]);

        return $pdf->setPaper('letter', 'landscape');
    }

    private function filename(PaymentBatch $paymentBatch): string
    {
        return 'payment-batch-' . $paymentBatch->id . '-' . $paymentBatch->payment_date->format('Y-m-d') . '.pdf';
    }
}
