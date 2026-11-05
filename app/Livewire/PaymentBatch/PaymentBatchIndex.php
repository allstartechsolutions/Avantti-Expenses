<?php

namespace App\Livewire\PaymentBatch;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\PaymentBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentBatchIndex extends Component
{
    use AuthorizesAbility;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    /** The batch's project manager (client request 9, Nov 2026). */
    public string $pmFilter = '';

    /** Payment date range (client request 1, Nov 2026). */
    public string $fromDate = '';

    public string $toDate = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'pmFilter' => ['except' => ''],
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
    ];

    public function updating($name): void
    {
        if (in_array($name, ['pmFilter', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'pmFilter', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->pmFilter !== '' || $this->fromDate !== '' || $this->toDate !== '';
    }

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

        if (! $batch->isDraft()) {
            session()->flash('error', __('Only draft batches can be deleted.'));

            return;
        }

        $batch->items()->delete();
        $batch->delete();

        session()->flash('message', __('Payment batch deleted successfully!'));
    }

    /** Every filter on the screen; the list and the totals read the same set. */
    protected function filteredQuery(): Builder
    {
        return PaymentBatch::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->pmFilter === 'none', fn ($q) => $q->whereNull('project_manager_id'))
            ->when($this->pmFilter !== '' && $this->pmFilter !== 'none', fn ($q) => $q->where('project_manager_id', $this->pmFilter))
            ->when($this->fromDate, fn ($q) => $q->whereDate('payment_date', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('payment_date', '<=', $this->toDate));
    }

    /**
     * Totals over every batch the filters keep — not only this page — by
     * project manager (each split by payment date) and by payment date.
     * The same sum as the Total Amount column: every line in the batch.
     */
    protected function totals(): array
    {
        $rows = $this->filteredQuery()
            ->with('projectManager:id,name')
            ->withSum('items', 'amount')
            ->get(['id', 'payment_date', 'project_manager_id']);

        $amount = fn (Collection $set) => round($set->sum('items_sum_amount') / 100, 2);

        $byDate = $rows->groupBy(fn ($b) => $b->payment_date->toDateString())
            ->sortKeys()
            ->map(fn ($set, $date) => ['date' => $set->first()->payment_date, 'count' => $set->count(), 'total' => $amount($set)])
            ->values();

        $byPm = $rows->groupBy(fn ($b) => $b->project_manager_id ?? 0)
            ->map(fn ($set) => [
                'name' => $set->first()->projectManager?->name,
                'count' => $set->count(),
                'total' => $amount($set),
                'dates' => $set->groupBy(fn ($b) => $b->payment_date->toDateString())
                    ->sortKeys()
                    ->map(fn ($d) => ['date' => $d->first()->payment_date, 'count' => $d->count(), 'total' => $amount($d)])
                    ->values(),
            ])
            ->sortBy(fn ($pm) => $pm['name'] === null ? "\u{10FFFF}" : mb_strtolower($pm['name']))
            ->values();

        return ['byDate' => $byDate, 'byPm' => $byPm, 'count' => $rows->count(), 'total' => $amount($rows)];
    }

    public function render()
    {
        $batches = $this->filteredQuery()
            ->with(['createdBy', 'client', 'project', 'subcontractor', 'projectManager', 'supervisor'])
            ->withCount('items')
            ->withSum('items', 'amount')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

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
            'totals' => $this->totals(),
            'projectManagers' => User::whereIn('id', PaymentBatch::whereNotNull('project_manager_id')->select('project_manager_id'))
                ->orderBy('name')->get(['id', 'name']),
        ])->layout('components.layouts.app');
    }
}
