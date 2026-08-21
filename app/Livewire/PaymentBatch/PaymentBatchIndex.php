<?php

namespace App\Livewire\PaymentBatch;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\PaymentBatch;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentBatchIndex extends Component
{
    use AuthorizesAbility;

    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function deleteBatch(int $batchId): void
    {
        $this->authorizeAbility('payments.batch');

        $batch = PaymentBatch::findOrFail($batchId);

        if (!$batch->isDraft()) {
            session()->flash('error', __('Only draft batches can be deleted.'));
            return;
        }

        $batch->items()->delete();
        $batch->delete();

        session()->flash('message', __('Payment batch deleted successfully!'));
    }

    public function render()
    {
        $query = PaymentBatch::query()
            ->with(['createdBy', 'client', 'project', 'subcontractor', 'projectManager'])
            ->withCount('items')
            ->withSum('items', 'amount')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            });

        $batches = $query->orderBy('created_at', 'desc')->paginate(10);

        $statusCounts = [
            'all' => PaymentBatch::count(),
            'draft' => PaymentBatch::where('status', 'draft')->count(),
            'partially_approved' => PaymentBatch::where('status', 'partially_approved')->count(),
            'approved' => PaymentBatch::where('status', 'approved')->count(),
            'cancelled' => PaymentBatch::where('status', 'cancelled')->count(),
        ];

        return view('livewire.payment-batch.payment-batch-index', [
            'batches' => $batches,
            'statusCounts' => $statusCounts,
        ])->layout('components.layouts.app');
    }
}
