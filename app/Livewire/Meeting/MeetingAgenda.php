<?php

namespace App\Livewire\Meeting;

use App\Livewire\Concerns\ManagesTasks;
use App\Livewire\Concerns\RaisesAgendaItems;
use App\Models\JobSite;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Project;
use App\Models\Task;
use App\Services\MeetingAgendaService;
use App\Services\TaskService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The agenda builder — the screen the whole module was designed around.
 *
 * Three ways onto the agenda:
 *   1. carry-forward, proposed automatically from earlier meetings of this
 *      series and every one of them still open;
 *   2. adding a project or job site, which brings all of *its* open tracked
 *      tasks with it, and offers the ones no meeting has discussed separately;
 *   3. raising something new, which may create a task then and there.
 *
 * See docs/meetings-module-plan.md §5.2.
 */
class MeetingAgenda extends Component
{
    use ManagesTasks, RaisesAgendaItems;

    public Meeting $meeting;

    /** Task ids ticked in the carry-forward panel. */
    public array $carrySelected = [];

    /** The location picker. */
    public string $addProjectId = '';
    public string $addJobSiteId = '';

    /** Which "not on the agenda" drawers are open, keyed by scope. */
    public array $openDrawers = [];

    public function mount(Meeting $meeting): void
    {
        abort_unless(
            auth()->user()?->is_admin || auth()->user()?->is_manager,
            403,
            'Manager or administrator access required.'
        );

        abort_unless($meeting->isDraft(), 403, 'This minute is published and its agenda can no longer be changed.');

        $this->meeting = $meeting->load('series');

        // Everything still open is proposed ticked: the default is that an open
        // item gets discussed, and the chair unticks what they will not reach.
        $this->carrySelected = $this->carryForward->pluck('id')->all();
    }

    protected function agenda(): MeetingAgendaService
    {
        return app(MeetingAgendaService::class);
    }

    // =========================================================================
    // WHAT IS PROPOSED
    // =========================================================================

    #[Computed]
    public function carryForward(): Collection
    {
        return $this->agenda()->carryForwardCandidates($this->meeting);
    }

    /** Carry-forward rows grouped the way they are read: by location. */
    #[Computed]
    public function carryForwardByScope(): Collection
    {
        return $this->carryForward->groupBy(fn (Task $task) => $task->getScopeLabel());
    }

    #[Computed]
    public function items(): Collection
    {
        return $this->meeting->items()
            ->with([
                'task.owner', 'task.assignees', 'project', 'jobSite',
                'children.task.owner', 'children.project', 'children.jobSite',
                'carriedFrom.meeting',
            ])
            ->get();
    }

    /** Locations already on the agenda, plus whatever the series covers. */
    #[Computed]
    public function scopesOnAgenda(): Collection
    {
        $fromItems = $this->items->flatMap(fn (MeetingItem $item) => collect([$item])->concat($item->children))
            ->filter(fn (MeetingItem $item) => $item->project_id !== null)
            ->map(fn (MeetingItem $item) => [
                'project_id' => $item->project_id,
                'job_site_id' => $item->job_site_id,
                'label' => $item->getScopeLabel(),
            ]);

        return $fromItems
            ->concat($this->agenda()->suggestedScopes($this->meeting))
            ->unique(fn (array $scope) => $scope['project_id'].'-'.($scope['job_site_id'] ?? 'p'))
            ->values();
    }

    /**
     * For each location on the agenda, what is still open there and not on it.
     * The tracked ones are offered as a bulk add; the direct ones sit behind a
     * drawer, because the minute is what management committed to, not
     * everyone's to-do list.
     */
    #[Computed]
    public function scopeCandidates(): Collection
    {
        return $this->scopesOnAgenda->mapWithKeys(function (array $scope) {
            $key = $scope['project_id'].'-'.($scope['job_site_id'] ?? 'p');

            return [$key => $scope + $this->agenda()->scopeCandidates(
                $this->meeting,
                $scope['project_id'],
                $scope['job_site_id']
            )];
        })->filter(fn (array $scope) => $scope['tracked']->isNotEmpty() || $scope['direct']->isNotEmpty());
    }

    #[Computed]
    public function addJobSites(): Collection
    {
        return $this->addProjectId
            ? JobSite::where('project_id', $this->addProjectId)->orderBy('job_site_name')->get(['id', 'job_site_name'])
            : collect();
    }

    #[Computed]
    public function counts(): array
    {
        $all = $this->items->flatMap(fn (MeetingItem $item) => collect([$item])->concat($item->children));

        return [
            'items' => $all->count(),
            'actions' => $all->where('type', 'action')->count(),
            'carried' => $all->filter(fn (MeetingItem $item) => $item->isCarriedForward())->count(),
            'overdue' => $all->filter(fn (MeetingItem $item) => $item->task?->isOverdue())->count(),
            'proposed' => $this->carryForward->count(),
        ];
    }

    // =========================================================================
    // CARRY FORWARD
    // =========================================================================

    public function toggleCarry(int $taskId): void
    {
        if (in_array($taskId, $this->carrySelected, true)) {
            $this->carrySelected = array_values(array_diff($this->carrySelected, [$taskId]));

            return;
        }

        $this->carrySelected[] = $taskId;
    }

    public function selectAllCarry(): void
    {
        $this->carrySelected = $this->carryForward->pluck('id')->all();
    }

    public function selectNoCarry(): void
    {
        $this->carrySelected = [];
    }

    /** Only the ones that are already late — the usual short agenda. */
    public function selectOverdueCarry(): void
    {
        $this->carrySelected = $this->carryForward->filter->isOverdue()->pluck('id')->all();
    }

    public function addSelectedCarry(): void
    {
        $tasks = $this->carryForward->whereIn('id', $this->carrySelected);

        foreach ($tasks as $task) {
            $this->agenda()->addTask($this->meeting, $task, auth()->user());
        }

        $this->carrySelected = [];
        $this->refreshAgenda();

        session()->flash('message', trans_choice(
            ':count item carried onto the agenda.|:count items carried onto the agenda.',
            $tasks->count(),
            ['count' => $tasks->count()]
        ));
    }

    // =========================================================================
    // ADDING A LOCATION
    // =========================================================================

    public function updatedAddProjectId(): void
    {
        $this->addJobSiteId = '';
    }

    /**
     * Put a location on the agenda. Its open tracked tasks come with it — the
     * behaviour this module exists for.
     */
    public function addScope(): void
    {
        $this->validate([
            'addProjectId' => ['required', 'integer', 'exists:projects,id'],
            'addJobSiteId' => ['nullable', 'integer', 'exists:job_sites,id'],
        ]);

        $candidates = $this->agenda()->scopeCandidates(
            $this->meeting,
            (int) $this->addProjectId,
            $this->addJobSiteId ? (int) $this->addJobSiteId : null
        );

        foreach ($candidates['tracked'] as $task) {
            $this->agenda()->addTask($this->meeting, $task, auth()->user());
        }

        $added = $candidates['tracked']->count();
        $left = $candidates['direct']->count();

        $this->addProjectId = '';
        $this->addJobSiteId = '';
        $this->refreshAgenda();

        session()->flash('message', $added > 0
            ? trans_choice(
                ':count open item came with that location.|:count open items came with that location.',
                $added, ['count' => $added])
            : ($left > 0
                ? __('Nothing tracked in meetings there yet. :count open task(s) are listed below, off the agenda.', ['count' => $left])
                : __('Nothing open at that location.')));
    }

    public function addTaskToAgenda(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        $this->agenda()->addTask($this->meeting, $task, auth()->user());
        $this->refreshAgenda();

        session()->flash('message', __('Task :code added to the agenda.', ['code' => $task->code()]));
    }

    public function addAllTracked(string $scopeKey): void
    {
        $scope = $this->scopeCandidates[$scopeKey] ?? null;

        if (! $scope) {
            return;
        }

        foreach ($scope['tracked'] as $task) {
            $this->agenda()->addTask($this->meeting, $task, auth()->user());
        }

        $this->refreshAgenda();
    }

    public function toggleDrawer(string $scopeKey): void
    {
        $this->openDrawers = in_array($scopeKey, $this->openDrawers, true)
            ? array_values(array_diff($this->openDrawers, [$scopeKey]))
            : [...$this->openDrawers, $scopeKey];
    }

    // =========================================================================
    // ORDERING AND REMOVING
    // =========================================================================

    /**
     * A row was dragged to a new place.
     *
     * The browser sends the order it now shows; the server keeps only the ids
     * that belong to this agenda at that level.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderItems(array $orderedIds, ?int $parentId = null): void
    {
        $this->agenda()->reorder($this->meeting, $orderedIds, $parentId);
        $this->refreshAgenda();
    }

    public function moveItem(int $itemId, string $direction): void
    {
        $this->agenda()->move(MeetingItem::findOrFail($itemId), $direction);
        $this->refreshAgenda();
    }

    public function removeItem(int $itemId): void
    {
        $item = MeetingItem::findOrFail($itemId);

        $this->agenda()->removeItem($item);
        $this->refreshAgenda();

        session()->flash('message', __('Taken off the agenda. The task itself is untouched and stays open.'));
    }

    protected function afterItemRaised(\App\Models\MeetingItem $item): void
    {
        $this->refreshAgenda();
    }

    protected function refreshAgenda(): void
    {
        unset($this->items, $this->counts, $this->carryForward, $this->carryForwardByScope,
            $this->scopesOnAgenda, $this->scopeCandidates);
    }

    public function render()
    {
        return view('livewire.meeting.meeting-agenda')->layout('components.layouts.app');
    }
}
