<?php

namespace App\Livewire\Concerns;

use App\Models\JobSite;
use App\Models\Project;
use App\Models\Rfi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The RFI list, shared by the project page and the job-site page.
 *
 * The two levels show the same list of the same records, differing only in
 * what they are scoped to and whether a Location column is worth showing —
 * which is why the query lives here once rather than in two components that
 * drift apart (docs/project-jobsite-parity-rule.md).
 *
 * The component supplying the context also supplies the permission scope, so
 * every guard in here is answered against the project or the job site the
 * person is actually looking at.
 */
trait ManagesRfis
{
    use AuthorizesAbility;

    public string $rfiSearch = '';

    public string $rfiStatusFilter = 'live';

    public string $rfiDisciplineFilter = 'all';

    public string $rfiLocationFilter = 'all';

    public string $rfiBallInCourtFilter = 'all';

    public bool $rfiOverdueOnly = false;

    public bool $rfiImpactOnly = false;

    /** The project this list belongs to. */
    abstract protected function contextProject(): Project;

    /** The job site it is fixed to, or null on a project page. */
    abstract protected function contextJobSite(): ?JobSite;

    /** What guards are answered against: the narrower of the two. */
    protected function permissionScope(): Project|JobSite
    {
        return $this->contextJobSite() ?? $this->contextProject();
    }

    public function updatedRfiSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRfiStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRfiDisciplineFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRfiLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRfiBallInCourtFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRfiOverdueOnly(): void
    {
        $this->resetPage();
    }

    public function updatedRfiImpactOnly(): void
    {
        $this->resetPage();
    }

    public function clearRfiFilters(): void
    {
        $this->reset([
            'rfiSearch',
            'rfiStatusFilter',
            'rfiDisciplineFilter',
            'rfiLocationFilter',
            'rfiBallInCourtFilter',
            'rfiOverdueOnly',
            'rfiImpactOnly',
        ]);

        $this->resetPage();
    }

    /** True when anything is narrowing the list — so the screen can say so. */
    public function hasRfiFilters(): bool
    {
        return $this->rfiSearch !== ''
            || $this->rfiStatusFilter !== 'live'
            || $this->rfiDisciplineFilter !== 'all'
            || $this->rfiLocationFilter !== 'all'
            || $this->rfiBallInCourtFilter !== 'all'
            || $this->rfiOverdueOnly
            || $this->rfiImpactOnly;
    }

    /**
     * The list, narrowed to what this person may see and then to the filters.
     *
     * `visibleTo()` first and always. A guard answers "may you open this
     * one?"; only this answers "which ones may you see?", and the counts below
     * are built from the same query for the same reason — a total over records
     * somebody cannot open is a leak by aggregate.
     */
    protected function rfiQuery(): Builder
    {
        $query = Rfi::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->contextProject()->id);

        if ($jobSite = $this->contextJobSite()) {
            $query->where('job_site_id', $jobSite->id);
        } elseif ($this->rfiLocationFilter === 'project') {
            $query->whereNull('job_site_id');
        } elseif ($this->rfiLocationFilter !== 'all') {
            $query->where('job_site_id', $this->rfiLocationFilter);
        }

        if ($this->rfiSearch !== '') {
            $term = '%'.$this->rfiSearch.'%';

            $query->where(fn (Builder $q) => $q
                ->where('number', 'like', $term)
                ->orWhere('subject', 'like', $term)
                ->orWhere('question', 'like', $term)
                ->orWhere('drawing_ref', 'like', $term)
                ->orWhere('spec_section', 'like', $term));
        }

        if ($this->rfiStatusFilter === 'live') {
            $query->live();
        } elseif ($this->rfiStatusFilter !== 'all') {
            $query->where('status', $this->rfiStatusFilter);
        }

        if ($this->rfiDisciplineFilter !== 'all') {
            $query->where('discipline', $this->rfiDisciplineFilter);
        }

        if ($this->rfiBallInCourtFilter === 'mine') {
            $query->waitingOn(auth()->id());
        } elseif ($this->rfiBallInCourtFilter === 'nobody') {
            $query->whereNull('ball_in_court_id');
        } elseif ($this->rfiBallInCourtFilter !== 'all') {
            $query->waitingOn((int) $this->rfiBallInCourtFilter);
        }

        if ($this->rfiOverdueOnly) {
            $query->overdue();
        }

        // Only offered to somebody who may see impact at all — see
        // rfiCanSeeImpact(). Without the grant the checkbox is not rendered
        // and this branch is unreachable, but it costs nothing to be sure.
        if ($this->rfiImpactOnly && $this->rfiCanSeeImpact()) {
            $query->where(fn (Builder $q) => $q->where('cost_impact', true)->orWhere('schedule_impact', true));
        }

        return $query;
    }

    /** @return LengthAwarePaginator<Rfi> */
    protected function rfis(): LengthAwarePaginator
    {
        return $this->rfiQuery()
            ->with(['jobSite:id,job_site_name', 'ballInCourt:id,name', 'createdBy:id,name'])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Rfi::DRAFT])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'rfiPage');
    }

    /**
     * The counters above the list.
     *
     * Built from the same visibleTo-narrowed query as the list itself, minus
     * the filters — so the numbers describe what this person may see, not what
     * exists.
     */
    protected function rfiSummary(): array
    {
        $base = fn () => Rfi::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->contextProject()->id)
            ->when($this->contextJobSite(), fn (Builder $q, JobSite $s) => $q->where('job_site_id', $s->id));

        return [
            'live' => $base()->live()->count(),
            'waiting_on_me' => $base()->live()->waitingOn(auth()->id())->count(),
            'overdue' => $base()->overdue()->count(),
            'closed' => $base()->where('status', Rfi::CLOSED)->count(),
        ];
    }

    /** Whether cost and schedule impact may be shown to this person at all. */
    public function rfiCanSeeImpact(): bool
    {
        return $this->allowsAbility('rfis.view_impact', $this->permissionScope());
    }

    /**
     * The people a list can be filtered by.
     *
     * Whoever currently holds one of the RFIs on screen, rather than every
     * user in the company — a filter offering names that match nothing is
     * noise, and on a guest's screen it would also be a staff directory.
     */
    protected function rfiBallInCourtOptions(): array
    {
        return Rfi::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->contextProject()->id)
            ->when($this->contextJobSite(), fn (Builder $q, JobSite $s) => $q->where('job_site_id', $s->id))
            ->whereNotNull('ball_in_court_id')
            ->with('ballInCourt:id,name')
            ->get()
            ->pluck('ballInCourt')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** The disciplines actually in use here, for the filter. */
    protected function rfiDisciplineOptions(): array
    {
        return Rfi::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->contextProject()->id)
            ->when($this->contextJobSite(), fn (Builder $q, JobSite $s) => $q->where('job_site_id', $s->id))
            ->whereNotNull('discipline')
            ->distinct()
            ->orderBy('discipline')
            ->pluck('discipline')
            ->all();
    }
}
