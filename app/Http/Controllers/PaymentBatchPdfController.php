<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PaymentBatch;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * One payment batch on paper — the header, every line with what it pays,
 * and the approved / pending / rejected totals. Guarded on the route with
 * the same grant as the batch screen.
 */
class PaymentBatchPdfController extends Controller
{
    public function download(PaymentBatch $paymentBatch)
    {
        return $this->pdf($paymentBatch)->download($this->filename($paymentBatch));
    }

    public function stream(PaymentBatch $paymentBatch)
    {
        return $this->pdf($paymentBatch)->stream($this->filename($paymentBatch));
    }

    private function pdf(PaymentBatch $paymentBatch)
    {
        $paymentBatch->load(['client', 'project', 'subcontractor', 'projectManager', 'createdBy', 'approvedBy']);

        $items = $paymentBatch->items()
            ->with(['contract.project.client', 'contract.jobSite', 'contract.subcontractor', 'scheduleItem', 'measurement'])
            ->orderBy('status')
            ->get();

        $cents = fn (?string $status = null) => ($status ? $items->where('status', $status) : $items)
            ->sum(fn ($item) => $item->getRawOriginal('amount')) / 100;

        $pdf = Pdf::loadView('pdf.payment-batch', [
            'batch' => $paymentBatch,
            'items' => $items,
            'summary' => [
                'total_amount' => $cents(),
                'approved_amount' => $cents('approved'),
                'pending_amount' => $cents('pending'),
                'rejected_amount' => $cents('rejected'),
            ],
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
