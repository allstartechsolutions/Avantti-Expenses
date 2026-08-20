<?php

namespace App\Livewire\Concerns;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * The task list for one project or one job site.
 *
 * Used by both levels from one place, on purpose: the parity rule
 * (docs/project-jobsite-parity-rule.md) is easier to keep by construction than
 * by remembering. The only difference the two pages have is that a project
 * rolls up its job sites' tasks and can filter by them.
 */
trait ListsScopedTasks
{
    public string $search = '';

    public string $statusFilter = '';

    public string $priorityFilter = '';

    public string $ownerFilter = '';

    public string $jobSiteFilter = '';

    /** '', 'meeting' or 'direct' — see Task::scopeMeetingTracked(). */
    public string $trackingFilter = '';

    public bool $showClosed = false;

    public function hasTaskFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || $this->priorityFilter !== ''
            || $this->ownerFilter !== ''
            || $this->jobSiteFilter !== ''
            || $this->trackingFilter !== ''
            || $this->showClosed;
    }

    public function clearTaskFilters(): void
    {
        $this->reset([
            'search', 'statusFilter', 'priorityFilter',
            'ownerFilter', 'jobSiteFilter', 'trackingFilter', 'showClosed',
        ]);
    }

    // =========================================================================
    // QUERIES
    // =========================================================================

    /**
     * Everything in scope, before the filters. A job site sees only its own; a
     * project sees its own and every one of its sites'.
     */
    protected function scopedTaskQuery(): Builder
    {
        $jobSite = $this->taskContextJobSite();

        return Task::query()
            ->when($jobSite, fn (Builder $q) => $q->where('job_site_id', $jobSite->id))
            ->unless($jobSite, fn (Builder $q) => $q->where('project_id', $this->taskContextProject()?->id));
    }

    protected function filteredTaskQuery(): Builder
    {
        return $this->scopedTaskQuery()
            ->when(! $this->showClosed, fn (Builder $q) => $q->open())
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->priorityFilter !== '', fn (Builder $q) => $q->where('priority', $this->priorityFilter))
            ->when($this->ownerFilter !== '', fn (Builder $q) => $q->where('owner_id', $this->ownerFilter))
            ->when($this->jobSiteFilter === 'project', fn (Builder $q) => $q->whereNull('job_site_id'))
            ->when($this->jobSiteFilter !== '' && $this->jobSiteFilter !== 'project',
                fn (Builder $q) => $q->where('job_site_id', $this->jobSiteFilter))
            ->when($this->trackingFilter === 'meeting', fn (Builder $q) => $q->meetingTracked())
            ->when($this->trackingFilter === 'direct', fn (Builder $q) => $q->direct())
            ->when($this->search !== '', function (Builder $q) {
                $term = '%'.$this->search.'%';

                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('number', 'like', trim($this->search, '#').'%');
                });
            });
    }

    /**
     * The same buckets My Tasks uses, so a task reads the same wherever it is
     * looked at.
     *
     * @return Collection<string, Collection<int, Task>>
     */
    #[Computed]
    public function groups(): Collection
    {
        $tasks = $this->filteredTaskQuery()
            ->with(['project', 'jobSite', 'owner', 'assignees'])
            ->withCount(['subtasks', 'meetingItems', 'notes'])
            ->orderByRaw('due_date is null, due_date asc')
            ->orderByDesc('id')
            ->get();

        $endOfWeek = now()->endOfWeek();

        $buckets = [
            'overdue' => collect(),
            'awaiting' => collect(),
            'this_week' => collect(),
            'later' => collect(),
            'no_date' => collect(),
            'closed' => collect(),
        ];

        foreach ($tasks as $task) {
            $bucket = match (true) {
                $task->isClosed() => 'closed',
                $task->isOverdue() => 'overdue',
                $task->status === 'ready' => 'awaiting',
                $task->due_date === null => 'no_date',
                $task->due_date->lte($endOfWeek) => 'this_week',
                default => 'later',
            };

            $buckets[$bucket]->push($task);
        }

        return collect($buckets)->filter(fn (Collection $rows) => $rows->isNotEmpty());
    }

    /**
     * The figures for this location, never narrowed by the filters.
     */
    #[Computed]
    public function stats(): array
    {
        $oldest = (clone $this->scopedTaskQuery())->open()->orderBy('created_at')->first();

        return [
            'open' => (clone $this->scopedTaskQuery())->open()->count(),
            'overdue' => (clone $this->scopedTaskQuery())->overdue()->count(),
            'awaiting' => (clone $this->scopedTaskQuery())->where('status', 'ready')->count(),
            'off_agenda' => (clone $this->scopedTaskQuery())->open()->direct()->count(),
            // The next date still ahead. Dates already passed are the overdue
            // figure next to it; showing one of those as "next due" reads as a
            // mistake.
            'next_due' => (clone $this->scopedTaskQuery())->open()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', now()->toDateString())
                ->min('due_date'),
            'oldest' => $oldest,
        ];
    }

    /** Only the people who actually own something here. */
    #[Computed]
    public function taskOwners(): Collection
    {
        return User::whereIn('id', (clone $this->scopedTaskQuery())->distinct()->pluck('owner_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
