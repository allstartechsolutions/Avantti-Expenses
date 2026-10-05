<?php

namespace App\Livewire\PaymentBatch;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\PaymentBatch;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentBatchShow extends Component
{
    use AuthorizesAbility;

    public PaymentBatch $paymentBatch;

    #[Computed]
    public function summary(): array
    {
        $items = $this->paymentBatch->items;
        $totalCents = $items->sum(fn ($item) => $item->getRawOriginal('amount'));
        $approvedCents = $items->where('status', 'approved')->sum(fn ($item) => $item->getRawOriginal('amount'));
        $pendingCents = $items->where('status', 'pending')->sum(fn ($item) => $item->getRawOriginal('amount'));
        $rejectedCents = $items->where('status', 'rejected')->sum(fn ($item) => $item->getRawOriginal('amount'));

        return [
            'total_amount' => $totalCents / 100,
            'approved_amount' => $approvedCents / 100,
            'pending_amount' => $pendingCents / 100,
            'rejected_amount' => $rejectedCents / 100,
        ];
    }

    /**
     * Every line of the batch as CSV, with the status totals underneath.
     * The same grant as the screen: the route has no mount() to carry it.
     */
    public function exportCsv(): StreamedResponse
    {
        $this->authorizeAbility('payments.batch');

        $batch = $this->paymentBatch;
        $items = $this->items();
        $summary = $this->summary();
        $money = fn ($value) => number_format((float) $value, 2, '.', '');

        $filename = 'payment-batch-' . $batch->id . '-' . $batch->payment_date->format('Y-m-d') . '.csv';

        return new StreamedResponse(function () use ($batch, $items, $summary, $money) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [__('Payment Batch'), $batch->name]);
            fputcsv($out, [__('Status'), __($batch->getStatusLabel())]);
            fputcsv($out, [__('Payment Date'), $batch->payment_date->appDate()]);
            fputcsv($out, [__('Created By'), $batch->createdBy?->name ?? '']);
            if ($batch->approved_at) {
                fputcsv($out, [__('Approved By'), $batch->approvedBy?->name ?? '']);
                fputcsv($out, [__('Approved At'), $batch->approved_at->appDateTime()]);
            }
            if ($batch->notes) {
                fputcsv($out, [__('Notes'), $batch->notes]);
            }
            fputcsv($out, []);

            fputcsv($out, [
                __('Subcontractor'), __('Client'), __('Project'), __('Job Site'), __('Contract #'),
                __('Pays'), __('Method'), __('Phase'), __('Notes'), __('Status'), __('Amount'),
            ]);

            foreach ($items as $item) {
                fputcsv($out, [
                    $item->contract->subcontractor?->company_name ?? '',
                    $item->contract->project->client?->company_name ?? '',
                    $item->contract->project->project_name,
                    $item->contract->jobSite?->job_site_name ?? __('Project General'),
                    $item->contract->contract_number,
                    $item->getPaysLabel(),
                    __($item->getPaymentMethodLabel()),
                    $item->phase ?? '',
                    $item->notes ?? '',
                    __($item->getStatusLabel()),
                    $money($item->amount),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('Approved'), $money($summary['approved_amount'])]);
            fputcsv($out, [__('Pending'), $money($summary['pending_amount'])]);
            fputcsv($out, [__('Rejected'), $money($summary['rejected_amount'])]);
            fputcsv($out, [__('Total'), $money($summary['total_amount'])]);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function items()
    {
        return $this->paymentBatch->items()
            ->with(['contract.project.client', 'contract.jobSite', 'contract.subcontractor', 'scheduleItem', 'measurement'])
            ->orderBy('status')
            ->get();
    }

    public function render()
    {
        $this->paymentBatch->load(['client', 'project', 'subcontractor', 'projectManager', 'supervisor', 'createdBy', 'approvedBy']);

        return view('livewire.payment-batch.payment-batch-show', [
            'items' => $this->items(),
        ])->layout('components.layouts.app');
    }
}
