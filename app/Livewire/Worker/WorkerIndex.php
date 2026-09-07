<?php

namespace App\Livewire\Worker;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\SubcontractorEmployee;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every worker known to the company — one per human, however many
 * subcontractors they have worked through — and the rows that look as if
 * they should be one. See docs/workers-module.md.
 */
class WorkerIndex extends Component
{
    use AuthorizesAbility;
    use WithPagination;

    public string $activeTab = 'workers';

    public string $search = '';

    /** '' | current | former */
    public string $status = '';

    /** '' | several | one */
    public string $companies = '';

    /** '' | differ */
    public string $taxIds = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'companies' => ['except' => ''],
        'taxIds' => ['except' => '', 'as' => 'tax'],
    ];

    public function mount(): void
    {
        $this->authorizeAbility('workers.view');
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['workers', 'suggestions'], true) ? $tab : 'workers';
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'status', 'companies', 'taxIds'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'companies', 'taxIds']);
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return trim($this->search) !== '' || $this->status !== '' || $this->companies !== '' || $this->taxIds !== '';
    }

    /**
     * Link every row of one suggested group as the same worker. The ids came
     * from the browser: they are re-read server-side and only rows at
     * different companies are joined.
     *
     * @param  array<int, int>  $employeeIds
     */
    public function linkGroup(array $employeeIds, ?string $reason = null): void
    {
        $this->authorizeAbility('workers.link');

        $rows = SubcontractorEmployee::with('worker')
            ->whereIn('id', array_map('intval', $employeeIds))
            ->get();

        if ($rows->count() < 2 || $rows->pluck('subcontractor_id')->unique()->count() < 2) {
            session()->flash('error', __('Nothing to link — these records were changed in another session. The list has been refreshed.'));

            return;
        }

        $first = $rows->shift();

        foreach ($rows as $row) {
            if ($row->subcontractor_id === $first->subcontractor_id) {
                continue;   // two rows at one company are two workers, or one twice — never linked here
            }

            $first->linkWith($row, Auth::user(), $reason);
        }

        session()->flash('message', __('Linked as one worker: :name.', ['name' => $first->worker?->name ?? $first->name]));
    }

    /**
     * Groups of rows at different companies that share a tax id, a phone or
     * an e-mail and are not already the same worker. A shared name alone is
     * not offered here — the vendor page suggests it when the row is typed,
     * where the person adding it can judge; on a list it would be noise.
     *
     * @return Collection<int, array{reason: string, key: string, rows: Collection<int, SubcontractorEmployee>}>
     */
    protected function suggestedGroups(): Collection
    {
        $employees = SubcontractorEmployee::with(['subcontractor', 'worker.employees.subcontractor'])
            ->orderBy('name')
            ->get();

        $groups = collect();

        foreach (['tax_id' => fn ($e) => Worker::normalizeTaxId($e->tax_id),
            'phone' => fn ($e) => Worker::normalizePhone($e->phone),
            'email' => fn ($e) => Worker::normalizeEmail($e->email)] as $reason => $key) {
            $employees
                ->groupBy($key)
                ->filter(fn ($rows, $k) => $k !== '' && $rows->pluck('subcontractor_id')->unique()->count() > 1)
                ->each(function ($rows, $k) use ($reason, &$groups) {
                    // Already the same worker, every one of them: nothing to suggest.
                    if ($rows->pluck('worker_id')->unique()->count() === 1) {
                        return;
                    }

                    $groups->push(['reason' => $reason, 'key' => (string) $k, 'rows' => $rows->values()]);
                });
        }

        // The same rows can match on two keys; keep the strongest one.
        return $groups
            ->unique(fn ($group) => $group['rows']->pluck('id')->sort()->join('-'))
            ->values();
    }

    /** Ids of the workers known at more than one company. */
    protected function severalCompaniesQuery()
    {
        return DB::table('subcontractor_employees')
            ->select('worker_id')
            ->whereNotNull('worker_id')
            ->groupBy('worker_id')
            ->havingRaw('COUNT(DISTINCT subcontractor_id) > 1');
    }

    public function render()
    {
        $term = trim($this->search);
        $today = now()->toDateString();

        $query = Worker::query()
            ->with(['employees.subcontractor', 'employees.linkedBy'])
            ->withCount(['employees', 'contracts'])
            ->when($term !== '', function (Builder $query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhereHas('employees', fn ($e) => $e->where('name', 'like', $like)
                            ->orWhere('tax_id', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhereHas('subcontractor', fn ($v) => $v->where('name', 'like', $like)));
                });
            })
            ->when($this->status === 'current', fn (Builder $q) => $q->whereHas('employees',
                fn ($e) => $e->whereNull('ended_at')->orWhereDate('ended_at', '>=', $today)))
            ->when($this->status === 'former', fn (Builder $q) => $q->whereDoesntHave('employees',
                fn ($e) => $e->whereNull('ended_at')->orWhereDate('ended_at', '>=', $today)))
            ->when($this->companies === 'several', fn (Builder $q) => $q->whereIn('id', $this->severalCompaniesQuery()))
            ->when($this->companies === 'one', fn (Builder $q) => $q->whereNotIn('id', $this->severalCompaniesQuery()));

        if ($this->taxIds === 'differ') {
            // Normalised comparison lives in PHP; the set is small.
            $ids = Worker::with('employees')->get()
                ->filter(fn (Worker $w) => $w->distinctTaxIds()->count() > 1)
                ->pluck('id');
            $query->whereIn('id', $ids);
        }

        $workers = $query->orderBy('name')->paginate(25);

        $suggestions = $this->activeTab === 'suggestions' ? $this->suggestedGroups() : collect();

        $all = Worker::with('employees')->get();

        $counts = [
            'workers' => $all->count(),
            'several' => $all->filter->isAtSeveralCompanies()->count(),
            'multiTaxId' => $all->filter(fn (Worker $w) => $w->distinctTaxIds()->count() > 1)->count(),
            'suggestions' => $this->activeTab === 'suggestions' ? $suggestions->count() : $this->suggestedGroups()->count(),
        ];

        return view('livewire.worker.worker-index', [
            'workers' => $workers,
            'suggestions' => $suggestions,
            'counts' => $counts,
        ])->layout('components.layouts.app');
    }
}
