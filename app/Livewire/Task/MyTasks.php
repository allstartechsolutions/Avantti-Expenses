<?php

namespace App\Livewire\Task;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * My Tasks — what one person is on the hook for.
 *
 * Three tabs, because "mine" means three different things: the ones I own and
 * am the only person who can declare ready, the ones I am helping with, and
 * the ones I raised and want to know the fate of.
 *
 * Rows are grouped by when they are due rather than sorted by it: a list that
 * opens on "3 overdue" is read differently from one that opens on a date
 * column.
 *
 * See docs/meetings-module-plan.md §5.5.
 */
class MyTasks extends Component
{
    use ManagesTasks;

    public string $tab = 'owned';

    public string $search = '';

    public string $statusFilter = '';

    public string $priorityFilter = '';

    public string $projectFilter = '';

    public bool $showClosed = false;

    protected $queryString = [
        'tab' => ['except' => 'owned'],
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'priorityFilter' => ['except' => ''],
        'projectFilter' => ['except' => ''],
        'showClosed' => ['except' => false],
    ];

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['owned', 'assigned', 'raised'], true) ? $tab : 'owned';
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || $this->priorityFilter !== ''
            || $this->projectFilter !== ''
            || $this->showClosed;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'priorityFilter', 'projectFilter', 'showClosed']);
    }

    // =========================================================================
    // QUERIES
    // =========================================================================

    /**
     * The base query for a tab, before the filters.
     */
    private function tabQuery(string $tab): Builder
    {
        $userId = auth()->id();

        return Task::query()
            ->when($tab === 'owned', fn (Builder $q) => $q->where('owner_id', $userId))
            ->when($tab === 'assigned', fn (Builder $q) => $q
                ->whereHas('assignees', fn (Builder $a) => $a->where('users.id', $userId))
                ->where('owner_id', '!=', $userId))
            ->when($tab === 'raised', fn (Builder $q) => $q->where('created_by', $userId));
    }

    private function filtered(Builder $query): Builder
    {
        return $query
            ->when(! $this->showClosed, fn (Builder $q) => $q->open())
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->priorityFilter !== '', fn (Builder $q) => $q->where('priority', $this->priorityFilter))
            ->when($this->projectFilter !== '', fn (Builder $q) => $q->where('project_id', $this->projectFilter))
            ->when($this->search !== '', function (Builder $q) {
                $term = '%'.$this->search.'%';

                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('number', 'like', trim($this->search, '#').'%');
                });
            });
    }

    #[Computed]
    public function tabCounts(): array
    {
        return [
            'owned' => $this->tabQuery('owned')->open()->count(),
            'assigned' => $this->tabQuery('assigned')->open()->count(),
            'raised' => $this->tabQuery('raised')->open()->count(),
        ];
    }

    /**
     * The list, split into the buckets a person actually reads: what is late,
     * what lands this week, what is waiting on someone else's confirmation,
     * and everything after that.
     *
     * @return Collection<string, Collection<int, Task>>
     */
    #[Computed]
    public function groups(): Collection
    {
        $tasks = $this->filtered($this->tabQuery($this->tab))
            ->with(['project', 'jobSite', 'owner', 'assignees'])
            ->withCount(['subtasks', 'meetingItems', 'notes'])
            ->withCount(['subtasks as open_subtasks_count' => fn (Builder $q) => $q->open()])
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
     * The figures at the top. Always the whole picture for this user, never
     * narrowed by the filters — a filtered total that looks like a workload is
     * worse than no total.
     */
    #[Computed]
    public function stats(): array
    {
        $userId = auth()->id();
        $mine = fn () => Task::query()->forUser($userId);

        return [
            'open' => (clone $mine())->open()->count(),
            'overdue' => (clone $mine())->overdue()->count(),
            'due_this_week' => (clone $mine())->open()
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now()->startOfDay(), now()->endOfWeek()])
                ->count(),
            // Admins and managers may confirm anything; everyone else only
            // what they chaired the meeting for.
            'awaiting_me' => Task::query()->where('status', 'ready')
                ->unless(
                    auth()->user()?->is_admin || auth()->user()?->is_manager,
                    fn (Builder $q) => $q->whereHas('originMeeting', fn (Builder $m) => $m->where('chair_id', $userId))
                )
                ->count(),
        ];
    }

    #[Computed]
    public function filterProjects(): Collection
    {
        return Project::whereIn(
            'id',
            Task::query()->forUser(auth()->id())->whereNotNull('project_id')->distinct()->pluck('project_id')
        )
            ->orderBy('project_name')
            ->get(['id', 'project_name']);
    }

    public function render()
    {
        return view('livewire.task.my-tasks')->layout('components.layouts.app');
    }
}
