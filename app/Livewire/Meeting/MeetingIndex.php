<?php

namespace App\Livewire\Meeting;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Meeting;
use App\Models\MeetingSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every meeting held, and every one being prepared.
 *
 * Company-level on purpose: a meeting spans as many projects as were on its
 * agenda, so it does not belong under any one of them.
 *
 * See docs/meetings-module-plan.md §5.1.
 */
class MeetingIndex extends Component
{
    use AuthorizesAbility;

    use WithPagination;

    public string $search = '';

    public string $seriesFilter = '';

    public string $statusFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'seriesFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSeriesFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->seriesFilter !== ''
            || $this->statusFilter !== ''
            || $this->fromDate !== ''
            || $this->toDate !== '';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'seriesFilter', 'statusFilter', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    public function canManage(): bool
    {
        return $this->allowsAbility('meetings.create');
    }

    // =========================================================================
    // QUERIES
    // =========================================================================

    public function meetings()
    {
        return Meeting::query()
            ->with(['series', 'chair'])
            ->withCount([
                'allItems as items_count',
                'attendees as present_count' => fn (Builder $q) => $q->where('attendance', 'present'),
                'attendees as invited_count',
            ])
            ->when($this->seriesFilter !== '', fn (Builder $q) => $q->where('meeting_series_id', $this->seriesFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->fromDate !== '', fn (Builder $q) => $q->whereDate('meeting_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $q) => $q->whereDate('meeting_date', '<=', $this->toDate))
            ->when($this->search !== '', function (Builder $q) {
                $term = '%'.$this->search.'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('number', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('location', 'like', $term));
            })
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->paginate(20);
    }

    #[Computed]
    public function seriesOptions(): Collection
    {
        return MeetingSeries::orderBy('name')->get(['id', 'name', 'code']);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'drafts' => Meeting::draft()->count(),
            'published' => Meeting::published()->count(),
            'next' => Meeting::active()
                ->whereDate('meeting_date', '>=', now()->toDateString())
                ->orderBy('meeting_date')
                ->first(),
            'series' => MeetingSeries::active()->count(),
        ];
    }

    public function render()
    {
        return view('livewire.meeting.meeting-index', [
            'meetings' => $this->meetings(),
        ])->layout('components.layouts.app');
    }
}
