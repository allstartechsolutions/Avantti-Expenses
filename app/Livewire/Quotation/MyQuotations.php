<?php

namespace App\Livewire\Quotation;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * My quotations — the buying work one person is on the hook for.
 *
 * An e-mail is one-shot; the work is multi-day. The assignment has to live
 * somewhere a person returns to, and this is it. Three groups, because
 * "mine" means three different things here:
 *
 *   1. **To start** — approved requisitions handed to me with no round yet.
 *      The oldest and the most urgent first: this is the queue the stall
 *      reminder chases.
 *   2. **In progress** — rounds I own or collaborate on, not yet awarded.
 *   3. **Unassigned** — approved requisitions nobody has been given, shown to
 *      anyone who may hand them out. This is the bucket that stops a null
 *      default becoming a silent hole: unassigned has to be a state you can
 *      *see*, not one you discover.
 *
 * Every query goes through `visibleTo()`. The page has no project of its own,
 * so there is no route to guard — an assignment must never widen what
 * somebody can see, and only a filter can guarantee that.
 *
 * See docs/procurement-assignment-plan.md, phase 6.
 */
class MyQuotations extends Component
{
    use AuthorizesAbility;

    public string $tab = 'to_start';

    public string $search = '';

    public string $projectFilter = '';

    protected $queryString = [
        'tab' => ['except' => 'to_start'],
        'search' => ['except' => ''],
        'projectFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        // Reaching the page at all needs to be able to see rounds somewhere;
        // the route middleware asks the same question.
        $this->authorizeAbility('quotations.view');

        if ($this->tab === 'unassigned' && ! $this->canSeeUnassigned()) {
            $this->tab = 'to_start';
        }
    }

    public function setTab(string $tab): void
    {
        $tab = in_array($tab, ['to_start', 'in_progress', 'unassigned'], true) ? $tab : 'to_start';

        // The bucket is only shown to somebody who can act on it — being told
        // about work you cannot pick up is noise.
        if ($tab === 'unassigned' && ! $this->canSeeUnassigned()) {
            return;
        }

        $this->tab = $tab;
    }

    /** Whoever may hand a requisition out gets to see the ones nobody has. */
    public function canSeeUnassigned(): bool
    {
        return $this->allowsAbility('requisitions.assign');
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->projectFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'projectFilter']);
    }

    public function updatedSearch(): void
    {
        unset($this->rows);
    }

    public function updatedProjectFilter(): void
    {
        unset($this->rows);
    }

    // =========================================================================
    // QUERIES
    // =========================================================================

    /** Approved requisitions handed to me that still have no round. */
    protected function toStartQuery(): Builder
    {
        return PurchaseRequisition::query()
            ->visibleTo(auth()->user())
            ->where('assigned_buyer_id', auth()->id())
            ->where('status', 'approved');
    }

    /** Rounds I own or am helping with, still on the table. */
    protected function inProgressQuery(): Builder
    {
        return Quotation::query()
            ->visibleTo(auth()->user())
            ->workedBy(auth()->id())
            ->whereNotIn('status', ['awarded', 'converted', 'cancelled']);
    }

    /** Approved requisitions nobody has been given. */
    protected function unassignedQuery(): Builder
    {
        return PurchaseRequisition::query()
            ->visibleTo(auth()->user())
            ->whereNull('assigned_buyer_id')
            ->where('status', 'approved');
    }

    protected function applyFilters(Builder $query, bool $isQuotation = false): Builder
    {
        return $query
            ->when($this->projectFilter !== '', fn (Builder $q) => $q->where('project_id', $this->projectFilter))
            ->when($this->search !== '', function (Builder $q) use ($isQuotation) {
                $term = '%'.trim($this->search).'%';
                $number = ltrim(trim($this->search), '#');

                $q->where(function (Builder $inner) use ($term, $number, $isQuotation) {
                    $inner->where('title', 'like', $term)
                        ->orWhere($isQuotation ? 'quotation_number' : 'requisition_number', 'like', '%'.$number.'%');
                });
            });
    }

    #[Computed]
    public function tabCounts(): array
    {
        return [
            'to_start' => $this->countWithoutRounds($this->toStartQuery()),
            'in_progress' => $this->inProgressQuery()->count(),
            'unassigned' => $this->canSeeUnassigned() ? $this->unassignedQuery()->count() : 0,
        ];
    }

    /**
     * Approved-and-unquoted, counted in SQL.
     *
     * `status === 'approved'` already implies no live round — the chain status
     * moves to `quoted` the moment one exists — but a stale status would put a
     * requisition somebody has already dealt with back on their list, so the
     * absence of a live round is checked rather than assumed.
     */
    protected function countWithoutRounds(Builder $query): int
    {
        return (clone $query)
            ->whereDoesntHave('quotations', fn (Builder $q) => $q->where('status', '!=', 'cancelled'))
            ->count();
    }

    /**
     * The rows for the current tab.
     *
     * Urgent first, then oldest — the two things that decide what a buyer
     * picks up next.
     *
     * @return Collection<int, mixed>
     */
    #[Computed]
    public function rows(): Collection
    {
        if ($this->tab === 'in_progress') {
            return $this->applyFilters($this->inProgressQuery(), isQuotation: true)
                ->with(['project', 'jobSite', 'assignedTo', 'assignees', 'quotationVendors', 'requisition'])
                ->withCount('items')
                ->orderByRaw('responses_due_at is null, responses_due_at asc')
                ->orderByDesc('id')
                ->get();
        }

        $query = $this->tab === 'unassigned' ? $this->unassignedQuery() : $this->toStartQuery();

        return $this->applyFilters($query)
            ->whereDoesntHave('quotations', fn (Builder $q) => $q->where('status', '!=', 'cancelled'))
            ->with(['project', 'jobSite', 'assignedBuyer', 'requestedBy', 'createdBy'])
            ->withCount('items')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderByRaw('needed_by is null, needed_by asc')
            ->orderBy('assigned_at')
            ->get();
    }

    /**
     * The figures at the top — always this person's whole picture, never
     * narrowed by the filters. A filtered total that looks like a workload is
     * worse than no total at all.
     */
    #[Computed]
    public function stats(): array
    {
        $toStart = $this->toStartQuery();

        return [
            'to_start' => $this->countWithoutRounds($toStart),
            'waiting_a_week' => $this->countWithoutRounds(
                (clone $toStart)->whereNotNull('assigned_at')->where('assigned_at', '<=', now()->subWeek())
            ),
            'in_progress' => $this->inProgressQuery()->count(),
            'responses_overdue' => (clone $this->inProgressQuery())
                ->whereIn('status', ['sent', 'comparing', 'negotiating'])
                ->whereNotNull('responses_due_at')
                ->whereDate('responses_due_at', '<', now()->toDateString())
                ->count(),
        ];
    }

    /** Only the projects this person actually has work on. */
    #[Computed]
    public function filterProjects(): Collection
    {
        $requisitionProjects = PurchaseRequisition::query()
            ->visibleTo(auth()->user())
            ->where(function (Builder $q) {
                $q->where('assigned_buyer_id', auth()->id());

                if ($this->canSeeUnassigned()) {
                    $q->orWhereNull('assigned_buyer_id');
                }
            })
            ->where('status', 'approved')
            ->distinct()
            ->pluck('project_id');

        $quotationProjects = Quotation::query()
            ->visibleTo(auth()->user())
            ->workedBy(auth()->id())
            ->distinct()
            ->pluck('project_id');

        return Project::whereIn('id', $requisitionProjects->merge($quotationProjects)->unique())
            ->orderBy('project_name')
            ->get(['id', 'project_name']);
    }

    /** Where a row's own screen lives — the job site's when it has one. */
    public function linkFor($record, string $kind): string
    {
        $route = $record->job_site_id
            ? route('jobsites.'.($kind === 'quotation' ? 'quotations' : 'requisitions'), $record->job_site_id)
            : route('projects.'.($kind === 'quotation' ? 'quotations' : 'requisitions'), $record->project_id);

        return $route.'?'.$kind.'='.$record->id;
    }

    /**
     * The number on the sidebar entry: approved requisitions handed to this
     * person that still have no round.
     *
     * Group 1 only. The rounds already in progress are work somebody is
     * getting on with; a badge is for work nobody has started, which is the
     * thing that quietly rots.
     *
     * Named from config/permissions.php, and deliberately cheap — it runs on
     * every page render.
     */
    public static function navBadge(\App\Models\User $user): int
    {
        if (app(\App\Services\PermissionResolver::class)->denies($user, 'quotations.view')) {
            return 0;
        }

        return PurchaseRequisition::query()
            ->visibleTo($user)
            ->where('assigned_buyer_id', $user->id)
            ->where('status', 'approved')
            ->whereDoesntHave('quotations', fn (Builder $q) => $q->where('status', '!=', 'cancelled'))
            ->count();
    }

    public function render()
    {
        return view('livewire.quotation.my-quotations')->layout('components.layouts.app');
    }
}
