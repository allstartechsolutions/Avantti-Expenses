<?php

namespace App\Livewire\PaymentBatch;

use App\Models\PaymentBatch;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PaymentBatchShow extends Component
{
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

    public function render()
    {
        $this->paymentBatch->load(['client', 'project', 'subcontractor', 'projectManager']);

        $items = $this->paymentBatch->items()
            ->with(['contract.project.client', 'contract.jobSite', 'contract.subcontractor'])
            ->orderBy('status')
            ->get();

        return view('livewire.payment-batch.payment-batch-show', [
            'items' => $items,
        ])->layout('components.layouts.app');
    }
}
