<?php

namespace App\Services;

use App\Models\JobSite;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Building the agenda of a meeting.
 *
 * The owner's requirement, in one sentence: *every time we add a project or job
 * site and it has an open task from the last meeting, it is supposed to show*.
 * That is what this class answers — what should be proposed for this meeting,
 * and what is deliberately left off.
 *
 * Two populations of task exist (docs/meetings-module-plan.md §4):
 *   meeting-tracked — discussed at least once, carries forward on its own
 *   direct          — raised on a project or job site page, never on an agenda
 *                     unless somebody puts it there
 *
 * Nothing here writes discussion or decisions; that is the meeting itself.
 */
class MeetingAgendaService
{
    // =========================================================================
    // WHAT SHOULD BE PROPOSED
    // =========================================================================

    /**
     * Open tasks this series has discussed before and has not closed.
     *
     * Deliberately every earlier meeting of the series, not only the last one:
     * an item skipped at one meeting must not disappear because nobody
     * mentioned it that week.
     *
     * @return Collection<int, Task>
     */
    public function carryForwardCandidates(Meeting $meeting): Collection
    {
        $previousMeetings = $this->previousMeetingIds($meeting);

        if ($previousMeetings->isEmpty()) {
            return collect();
        }

        $candidates = Task::query()
            ->open()
            ->whereHas('meetingItems', fn (Builder $q) => $q->whereIn('meeting_id', $previousMeetings))
            ->whereDoesntHave('meetingItems', fn (Builder $q) => $q->where('meeting_id', $meeting->id))
            ->with(['project', 'jobSite', 'owner', 'notes' => fn ($q) => $q->limit(1), 'meetingItems.meeting'])
            ->withCount('meetingItems')
            ->get();

        // Ordered the way they will land, so the panel and the agenda agree.
        // Sorting them here rather than in SQL is deliberate: the ranking
        // reads the previous meeting's positions, which no ORDER BY can see.
        return $this->sortCandidates($meeting, $candidates);
    }

    /**
     * The open tasks of one location, split the way the agenda treats them.
     *
     * @return array{tracked: Collection<int, Task>, direct: Collection<int, Task>}
     */
    public function scopeCandidates(Meeting $meeting, ?int $projectId, ?int $jobSiteId): array
    {
        $query = fn () => Task::query()
            ->open()
            ->when($jobSiteId, fn (Builder $q) => $q->where('job_site_id', $jobSiteId))
            ->unless($jobSiteId, fn (Builder $q) => $q->where('project_id', $projectId))
            ->whereDoesntHave('meetingItems', fn (Builder $q) => $q->where('meeting_id', $meeting->id))
            ->with(['project', 'jobSite', 'owner'])
            ->withCount('meetingItems');

        $keys = $this->orderKeys($meeting);

        return [
            'tracked' => $this->sortCandidates($meeting, $query()->meetingTracked()->get(), $keys),
            'direct' => $this->sortCandidates($meeting, $query()->direct()->get(), $keys),
        ];
    }

    /**
     * The same answer as `scopeCandidates()`, for every location at once.
     *
     * Asked one location at a time — which is how the agenda screen asked it —
     * this costs two task reads and their eager loads per location, so a
     * meeting covering five projects paid five times over. A meeting spanning
     * several projects is the ordinary case, not the extreme one.
     *
     * The two populations are told apart in memory rather than by a second
     * query: `meetingTracked()` and `direct()` ask whether a task has any
     * meeting line at all, and `withCount()` has already counted them.
     *
     * @param  Collection<int, array{project_id:?int, job_site_id:?int}>  $scopes
     * @return Collection<string, array{tracked: Collection<int, Task>, direct: Collection<int, Task>}>
     */
    public function scopeCandidatesFor(Meeting $meeting, Collection $scopes): Collection
    {
        if ($scopes->isEmpty()) {
            return collect();
        }

        // A location named by project takes everything on that project, its job
        // sites included; a location named by job site takes only that site.
        // Both halves are asked for together and separated afterwards.
        $projectIds = $scopes->whereNull('job_site_id')->pluck('project_id')->filter()->unique()->values();
        $jobSiteIds = $scopes->pluck('job_site_id')->filter()->unique()->values();

        $tasks = Task::query()
            ->open()
            ->where(fn (Builder $q) => $q
                ->when($projectIds->isNotEmpty(), fn (Builder $q) => $q->orWhereIn('project_id', $projectIds))
                ->when($jobSiteIds->isNotEmpty(), fn (Builder $q) => $q->orWhereIn('job_site_id', $jobSiteIds)))
            ->whereDoesntHave('meetingItems', fn (Builder $q) => $q->where('meeting_id', $meeting->id))
            ->with(['project', 'jobSite', 'owner'])
            ->withCount('meetingItems')
            ->get();

        $keys = $this->orderKeys($meeting);

        return $scopes->mapWithKeys(function (array $scope) use ($tasks, $meeting, $keys) {
            $here = $scope['job_site_id'] !== null
                ? $tasks->where('job_site_id', $scope['job_site_id'])
                : $tasks->where('project_id', $scope['project_id']);

            [$tracked, $direct] = $here->partition(
                fn (Task $task) => ($task->meeting_items_count ?? 0) > 0
            );

            return [$this->scopeKey($scope['project_id'], $scope['job_site_id']) => [
                'tracked' => $this->sortCandidates($meeting, $tracked->values(), $keys),
                'direct' => $this->sortCandidates($meeting, $direct->values(), $keys),
            ]];
        });
    }

    /**
     * The locations a meeting would normally cover: whatever its series says,
     * plus anything already on this agenda.
     *
     * @return Collection<int, array{project_id:int, job_site_id:?int, label:string}>
     */
    public function suggestedScopes(Meeting $meeting): Collection
    {
        $fromSeries = $meeting->series?->scopes()->with(['project', 'jobSite'])->get() ?? collect();

        return $fromSeries->map(fn ($scope) => [
            'project_id' => $scope->project_id,
            'job_site_id' => $scope->job_site_id,
            'label' => $scope->label(),
        ])->values();
    }

    // =========================================================================
    // BUILDING
    // =========================================================================

    /**
     * Put a task on the agenda, remembering which earlier item it continues so
     * the minute can say "open since SITE-2026-009".
     *
     * The line lands at the end of its own location's block rather than at the
     * bottom of the agenda: the whole point of the ordering work is that a
     * project's items stay together.
     */
    public function addTask(Meeting $meeting, Task $task, User $actor): MeetingItem
    {
        $this->assertBuildable($meeting);

        // Already there — adding it twice would give the same task two lines.
        if ($existing = $meeting->allItems()->where('task_id', $task->id)->first()) {
            return $existing;
        }

        return DB::transaction(fn () => $this->createTaskItem(
            $meeting,
            $task,
            $actor,
            null,
            $this->openPositions($meeting, $task->project_id, $task->job_site_id, 1),
            $this->previousMeetingIds($meeting)->all(),
        ));
    }

    /**
     * Carry a set of tasks onto the agenda in one go.
     *
     * Doing the whole set together is what makes the order and the nesting
     * survive: the sort needs to see every task at once, parents have to exist
     * before their children can hang off them, and each location's rows are
     * inserted as one block instead of one shift per row.
     *
     * @param  Collection<int, Task>  $tasks
     * @return int  how many lines were created
     */
    public function carryForward(Meeting $meeting, Collection $tasks, User $actor): int
    {
        $this->assertBuildable($meeting);

        if ($tasks->isEmpty()) {
            return 0;
        }

        $sorted = $this->sortCandidates($meeting, $tasks);
        $previousIds = $this->previousMeetingIds($meeting)->all();

        // The line each task came from, most recent first sighting winning.
        // The *line* is the unit of carry, not the task: it is what knows which
        // main item the work sat under.
        $lines = MeetingItem::whereIn('meeting_id', $previousIds)
            ->whereIn('task_id', $sorted->pluck('id'))
            ->orderBy('id')
            ->get()
            ->keyBy('task_id');

        $mains = MeetingItem::whereIn('id', $lines->pluck('parent_id')->filter()->unique())
            ->with(['task', 'project', 'jobSite'])
            ->get()
            ->keyBy('id');

        $units = $this->groupIntoUnits($sorted, $lines, $mains);
        $created = 0;

        DB::transaction(function () use ($meeting, $units, $actor, $previousIds, &$created) {
            // One block of positions per location, so a group lands whole and
            // in its own project's run.
            $byScope = $units->groupBy(fn (array $unit) => $this->scopeKey(
                $unit['scope']->project_id,
                $unit['scope']->job_site_id
            ));

            foreach ($byScope as $scopeUnits) {
                $scope = $scopeUnits->first()['scope'];
                $at = $this->openPositions($meeting, $scope->project_id, $scope->job_site_id, $scopeUnits->count());

                foreach ($scopeUnits->values() as $offset => $unit) {
                    $head = $this->createUnitHead($meeting, $unit, $actor, $at + $offset, $previousIds, $created);

                    foreach ($unit['children'] as $index => $child) {
                        $this->createTaskItem($meeting, $child, $actor, $head, $index, $previousIds);
                        $created++;
                    }
                }
            }
        });

        return $created;
    }

    /**
     * The carry-forward candidates as the groups they will land as.
     *
     * The panel shows what the button will do, so a main item and its sub-items
     * are read together there too — including a main item that is not itself
     * open work and therefore has no tick of its own: it comes because its
     * sub-items do.
     *
     * @param  Collection<int, Task>|null  $candidates
     * @return Collection<int, array{scope: MeetingItem|Task, main: ?MeetingItem, task: ?Task, children: Collection<int, Task>}>
     */
    public function carryForwardUnits(Meeting $meeting, ?Collection $candidates = null): Collection
    {
        $candidates ??= $this->carryForwardCandidates($meeting);

        if ($candidates->isEmpty()) {
            return collect();
        }

        $previousIds = $this->previousMeetingIds($meeting)->all();

        $lines = MeetingItem::whereIn('meeting_id', $previousIds)
            ->whereIn('task_id', $candidates->pluck('id'))
            ->orderBy('id')
            ->get()
            ->keyBy('task_id');

        $mains = MeetingItem::whereIn('id', $lines->pluck('parent_id')->filter()->unique())
            ->with(['task', 'project', 'jobSite'])
            ->get()
            ->keyBy('id');

        return $this->groupIntoUnits($candidates, $lines, $mains);
    }

    /**
     * Gather the tasks into what actually goes onto the agenda.
     *
     * A main item and the sub-items under it are **one group**, and the group
     * travels whole. The main line stands even when it is not itself a piece of
     * open work — an information line used as a heading, or an action whose own
     * task is finished while the work under it is not. Promoting the sub-items
     * and dropping the heading, which is what this used to do, loses the shape
     * the chair gave the agenda.
     *
     * @param  Collection<int, Task>  $sorted            candidates, already in landing order
     * @param  Collection<int, MeetingItem>  $lines      previous line, keyed by task id
     * @param  Collection<int, MeetingItem>  $mains      previous main lines, keyed by id
     * @return Collection<int, array{scope: MeetingItem|Task, main: ?MeetingItem, task: ?Task, children: Collection<int, Task>}>
     */
    protected function groupIntoUnits(Collection $sorted, Collection $lines, Collection $mains): Collection
    {
        // Which main line, if any, each task belongs under. A task whose own
        // line is the head of a group is the head of that group rather than a
        // line of its own.
        $heads = $mains->filter(fn (MeetingItem $main) => $main->task_id !== null)
            ->keyBy('task_id');

        $units = collect();

        foreach ($sorted as $task) {
            // `get()` rather than `[$task->id]`: a collection offset on a
            // missing key warns and returns null, and Laravel turns that
            // warning into an exception — so the `?->` below never got its
            // chance. A task can genuinely have no earlier line here: the
            // tracked list offered for a location is everything this company
            // has discussed anywhere, and `$lines` only knows the previous
            // meetings of THIS series. At the first meeting of a series there
            // are none at all.
            $mainId = $lines->get($task->id)?->parent_id;

            // Head of a group: its own line has sub-items coming across.
            if ($mainId === null && $heads->has($task->id)) {
                $main = $heads[$task->id];

                $units->offsetExists('line:'.$main->id) or $units->put('line:'.$main->id, [
                    'scope' => $main, 'main' => $main, 'task' => null, 'children' => collect(),
                ]);

                $unit = $units['line:'.$main->id];
                $unit['task'] = $task;
                $units->put('line:'.$main->id, $unit);

                continue;
            }

            // A sub-item: it joins its main line's group, creating it if this
            // is the first of the group to be reached.
            if ($mainId !== null && $mains->has($mainId)) {
                $main = $mains[$mainId];

                $units->offsetExists('line:'.$main->id) or $units->put('line:'.$main->id, [
                    'scope' => $main,
                    'main' => $main,
                    // Filled in when the main line's own task is reached, which
                    // only happens while that task is still open.
                    'task' => null,
                    'children' => collect(),
                ]);

                $units['line:'.$main->id]['children']->push($task);

                continue;
            }

            // A line of its own.
            $units->put('task:'.$task->id, [
                'scope' => $task, 'main' => null, 'task' => $task, 'children' => collect(),
            ]);
        }

        return $units->values();
    }

    /**
     * The top line of a group, or a standalone line.
     *
     * When the main line is not itself open work it is copied across as it
     * stood — its title, its type, and its task if it had one, so a finished
     * heading reads as finished with the remaining work beneath it.
     */
    protected function createUnitHead(
        Meeting $meeting,
        array $unit,
        User $actor,
        int $position,
        array $previousIds,
        int &$created,
    ): MeetingItem {
        // Already on this agenda — carried by an earlier press, or added by
        // hand. Its sub-items join what is there rather than starting a second
        // copy of the same group.
        $existing = $unit['main'] === null ? null : $meeting->allItems()
            ->where(fn ($q) => $q
                ->where('carried_from_item_id', $unit['main']->id)
                ->when($unit['main']->task_id, fn ($q, $taskId) => $q->orWhere('task_id', $taskId)))
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($unit['task'] !== null) {
            $created++;

            return $this->createTaskItem($meeting, $unit['task'], $actor, null, $position, $previousIds);
        }

        $main = $unit['main'];
        $created++;

        return $meeting->allItems()->create([
            'position' => $position,
            'project_id' => $main->project_id,
            'job_site_id' => $main->job_site_id,
            'type' => $main->type,
            'title' => $main->task?->title ?? $main->title,
            'task_id' => $main->task_id,
            'carried_from_item_id' => $main->id,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * A line raised fresh at this meeting. An action item may carry a task
     * created with it — that is the normal way work leaves a meeting.
     */
    public function addItem(Meeting $meeting, array $data, User $actor, ?Task $task = null): MeetingItem
    {
        $this->assertBuildable($meeting);

        $parentId = $this->assertOwnParent($meeting, $data['parent_id'] ?? null);
        $projectId = $data['project_id'] ?? null;
        $jobSiteId = $data['job_site_id'] ?? null;

        // A sub-item belongs under its parent; a new top-level line belongs at
        // the end of its own location's block, not at the bottom of the page.
        $position = $parentId !== null
            ? $this->nextPosition($meeting, $parentId)
            : $this->openPositions($meeting, $projectId, $jobSiteId, 1);

        return $meeting->allItems()->create([
            'parent_id' => $parentId,
            'position' => $position,
            'project_id' => $data['project_id'] ?? null,
            'job_site_id' => $data['job_site_id'] ?? null,
            'type' => $data['type'] ?? 'information',
            'title' => $data['title'],
            'discussion' => $data['discussion'] ?? null,
            'task_id' => $task?->id,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Take a line off the agenda.
     *
     * The task itself is untouched: it stays open and will be proposed again
     * next time. Removing a line is "we are not discussing this today", never
     * "this is done".
     */
    public function removeItem(MeetingItem $item): void
    {
        $this->assertBuildable($item->meeting);

        DB::transaction(function () use ($item) {
            $meetingId = $item->meeting_id;
            $parentId = $item->parent_id;

            $item->children()->delete();
            $item->delete();

            $this->resequence($meetingId, $parentId);
        });
    }

    /**
     * Take every line off a draft agenda at once.
     *
     * The same promise as removing one: **nothing is closed.** Every task stays
     * open and is proposed again next time, and the lines of other meetings are
     * untouched. It is for an agenda built wrongly, where twenty presses of ×
     * is not a reasonable ask.
     */
    public function clear(Meeting $meeting): int
    {
        $this->assertBuildable($meeting);

        return DB::transaction(function () use ($meeting) {
            $lines = MeetingItem::where('meeting_id', $meeting->id);
            $count = (clone $lines)->count();

            // Children first: the parent foreign key cascades, but deleting in
            // this order keeps the count honest and the cascade unused.
            MeetingItem::where('meeting_id', $meeting->id)->whereNotNull('parent_id')->delete();
            MeetingItem::where('meeting_id', $meeting->id)->whereNull('parent_id')->delete();

            return $count;
        });
    }

    /**
     * Move a line up or down among its siblings. The displayed number is
     * computed from position, so this is all reordering needs to touch.
     *
     * A top-level line **stops at the edge of its location's block.** A row
     * cannot change project by being moved — its location comes from its task —
     * so a swap across the boundary would only wedge it into another project's
     * run of positions and split the agenda into four headings where there were
     * two. Whole blocks move with `moveGroup()` instead.
     */
    public function move(MeetingItem $item, string $direction): void
    {
        $this->assertBuildable($item->meeting);

        $siblings = MeetingItem::where('meeting_id', $item->meeting_id)
            ->where('parent_id', $item->parent_id)
            ->orderBy('position')
            ->get();

        $index = $siblings->search(fn (MeetingItem $candidate) => $candidate->is($item));
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $siblings->count()) {
            return;
        }

        $other = $siblings[$target];

        if ($item->parent_id === null && ! $this->sameScope($item, $other)) {
            return;
        }

        DB::transaction(function () use ($item, $other) {
            $itemPosition = $item->position;

            $item->update(['position' => $other->position]);
            $other->update(['position' => $itemPosition]);
        });
    }

    /** Whether this line is already at the edge of its block, so the button can say so. */
    public function canMove(MeetingItem $item, string $direction): bool
    {
        $siblings = MeetingItem::where('meeting_id', $item->meeting_id)
            ->where('parent_id', $item->parent_id)
            ->orderBy('position')
            ->get();

        $index = $siblings->search(fn (MeetingItem $candidate) => $candidate->is($item));
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $siblings->count()) {
            return false;
        }

        return $item->parent_id !== null || $this->sameScope($item, $siblings[$target]);
    }

    /**
     * Move a whole location block above or below the one next to it.
     *
     * This is how the order of the projects themselves is changed, now that a
     * single row can no longer walk out of its own block.
     */
    public function moveGroup(Meeting $meeting, ?int $projectId, ?int $jobSiteId, string $direction): void
    {
        $this->assertBuildable($meeting);

        $blocks = $this->blocks($meeting);
        $key = $this->scopeKey($projectId, $jobSiteId);

        $index = $blocks->search(fn (array $block) => $block['key'] === $key);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $blocks->count()) {
            return;
        }

        $order = $blocks->values()->all();
        $moving = array_splice($order, $index, 1);
        array_splice($order, $target, 0, $moving);

        $this->writeRootPositions($meeting, collect($order)->flatMap(fn (array $block) => $block['items']));
    }

    /**
     * Bring each location's lines back together without disturbing the order
     * the chair put them in.
     *
     * The tidy button: it answers "my agenda is interleaved", not "sort it for
     * me". A row keeps its place relative to the rest of its own project.
     */
    public function regroup(Meeting $meeting): void
    {
        $this->assertBuildable($meeting);

        $this->writeRootPositions($meeting, $this->blocks($meeting, group: true)
            ->flatMap(fn (array $block) => $block['items']));
    }

    /**
     * Re-sort the whole agenda the way a freshly carried one would have landed:
     * locations in the previous meeting's order, and inside each of them the
     * late work first when asked for, then the previous meeting's order.
     *
     * Lines raised at this meeting have no previous position, so they hold
     * their place at the end of their own block rather than being shuffled
     * somewhere arbitrary.
     */
    public function applyOrder(Meeting $meeting, ?string $mode = null): void
    {
        $this->assertBuildable($meeting);

        $overdueFirst = ($mode ?? $meeting->series?->agenda_order) === 'overdue_first';
        $keys = $this->orderKeys($meeting);

        $roots = MeetingItem::where('meeting_id', $meeting->id)
            ->whereNull('parent_id')
            ->with(['task', 'children.task', 'project', 'jobSite'])
            ->orderBy('position')
            ->get();

        $sorted = $roots->sortBy(function (MeetingItem $item) use ($keys, $overdueFirst) {
            $group = $keys['groups'][$this->scopeKey($item->project_id, $item->job_site_id)] ?? null;
            $key = $item->task_id !== null ? ($keys['tasks'][$item->task_id] ?? null) : null;

            return [
                $group['tier'] ?? 2,
                $group['pos'] ?? 0,
                $item->getScopeLabel(),
                $overdueFirst && ! $this->isLate($item) ? 1 : 0,
                $key['tier'] ?? 2,
                $key['root'] ?? PHP_INT_MAX,
                (int) $item->position,
            ];
        });

        $this->writeRootPositions($meeting, $sorted->values());
    }



    /**
     * Put a set of siblings in the order given.
     *
     * Only ids that really are siblings on this meeting are honoured, and the
     * order is rewritten from what the server finds rather than trusted from
     * the browser — a dropped row must not be able to move somebody else's
     * agenda.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorder(Meeting $meeting, array $orderedIds, ?int $parentId = null): void
    {
        $this->assertBuildable($meeting);

        $siblings = MeetingItem::where('meeting_id', $meeting->id)
            ->where('parent_id', $parentId)
            ->orderBy('position')
            ->get()
            ->keyBy('id');

        $ordered = collect($orderedIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => $siblings->has($id))
            ->map(fn (int $id) => $siblings[$id]);

        // A drag happens inside one location block, so only rows of that
        // location are honoured. A row cannot change project by being dragged —
        // its location comes from its task — and letting a mixed list through
        // would break the grouping the whole ordering work exists to keep.
        if ($parentId === null && $ordered->isNotEmpty()) {
            $scope = $this->scopeKey($ordered->first()->project_id, $ordered->first()->job_site_id);

            $ordered = $ordered->filter(
                fn (MeetingItem $item) => $this->scopeKey($item->project_id, $item->job_site_id) === $scope
            );
        }

        if ($ordered->isEmpty()) {
            return;
        }

        // Only the slots those rows already occupy are rewritten. Anything the
        // browser left out — a stale page, a row in another block — keeps the
        // position it has, so a drag can never disturb the rest of the agenda.
        $slots = $ordered->pluck('position')->sort()->values();

        DB::transaction(function () use ($ordered, $slots) {
            foreach ($ordered->values() as $index => $item) {
                $item->update(['position' => $slots[$index]]);
            }
        });
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    // =========================================================================
    // ORDER
    //
    // Carrying items forward used to sort them by due date alone, which threw
    // away the order the chair had dragged the previous agenda into and mixed
    // the projects together — the complaint this section answers. An agenda now
    // groups by project / job site and keeps the previous meeting's order
    // inside each group. See docs/meetings-agenda-order-plan.md §2.
    // =========================================================================

    /**
     * Earlier meetings of this series that have not been published yet.
     *
     * A minute's figures are frozen when it is *published*, not when the
     * meeting is held (`MeetingService::publish()`), so while an earlier minute
     * is still a draft its screen follows the live tasks — and moving work on
     * from this agenda changes what that draft shows. Saying so is the whole of
     * the answer: see docs/meetings-agenda-order-plan.md §4.
     *
     * @return Collection<int, Meeting>
     */
    public function unpublishedEarlierMeetings(Meeting $meeting): Collection
    {
        $ids = $this->previousMeetingIds($meeting);

        if ($ids->isEmpty()) {
            return collect();
        }

        return Meeting::whereIn('id', $ids)
            ->where('status', 'draft')
            ->orderByDesc('meeting_date')
            ->get();
    }

    /**
     * Later meetings of this series that already have an agenda.
     *
     * Publishing while one of these exists stamps figures gathered *after* this
     * meeting onto this meeting's record, permanently. It is allowed — a minute
     * written up late is a fact of life — but it is not done silently.
     *
     * @return Collection<int, Meeting>
     */
    public function laterMeetingsWithItems(Meeting $meeting): Collection
    {
        if (! $meeting->meeting_series_id) {
            return collect();
        }

        return Meeting::where('meeting_series_id', $meeting->meeting_series_id)
            ->where('status', '!=', 'cancelled')
            ->whereKeyNot($meeting->id)
            ->where(fn (Builder $q) => $q
                ->whereDate('meeting_date', '>', $meeting->meeting_date)
                ->orWhere(fn (Builder $same) => $same
                    ->whereDate('meeting_date', '=', $meeting->meeting_date)
                    ->where('id', '>', $meeting->id)))
            ->whereHas('allItems')
            ->orderBy('meeting_date')
            ->get();
    }

    /** Ranks, computed once per service instance because the sort asks twice. */
    protected array $orderKeyCache = [];

    /**
     * The meeting whose shape this one copies: the most recent earlier meeting
     * of the series that actually has items.
     *
     * "Most recent with items" rather than simply "the last one" so a meeting
     * created and abandoned empty does not blank the ordering for the one
     * after it.
     */
    public function templateMeeting(Meeting $meeting): ?Meeting
    {
        return $this->previousMeetingsNewestFirst($meeting)
            ->first(fn (Meeting $previous) => $previous->all_items_count > 0);
    }

    /**
     * One key per location and per task, saying where each sat last time.
     *
     * Meetings are read newest first and the first sighting of a task or a
     * location wins, so an item skipped for a fortnight is ranked by the last
     * meeting that actually discussed it. Everything found outside the template
     * meeting is ranked one tier down, which puts it after the template's own
     * rows rather than interleaved with them.
     *
     * @return array{groups: array<string, array{tier:int, pos:int}>, tasks: array<int, array{tier:int, root:int, child:int, parent_task:?int}>}
     */
    protected function orderKeys(Meeting $meeting): array
    {
        if (isset($this->orderKeyCache[$meeting->id])) {
            return $this->orderKeyCache[$meeting->id];
        }

        $groups = [];
        $tasks = [];
        $tier = 0;

        $earlier = $this->previousMeetingsNewestFirst($meeting)
            ->filter(fn (Meeting $previous) => $previous->all_items_count > 0);

        if ($earlier->isEmpty()) {
            return $this->orderKeyCache[$meeting->id] = ['groups' => $groups, 'tasks' => $tasks];
        }

        // One read for the whole series. Read a meeting at a time, this was a
        // query per earlier meeting every time the agenda sorted — and a
        // weekly series a year old has fifty of them.
        $byMeeting = MeetingItem::whereIn('meeting_id', $earlier->modelKeys())
            ->orderBy('position')
            ->get()
            ->groupBy('meeting_id');

        foreach ($earlier as $previous) {
            $items = $byMeeting->get($previous->id) ?? collect();
            $roots = $items->whereNull('parent_id')->keyBy('id');

            foreach ($items as $item) {
                // A line is placed by its root: a sub-item travels with the
                // parent it hung off, wherever that parent sat.
                $root = $item->parent_id !== null ? ($roots[$item->parent_id] ?? null) : $item;

                if ($root === null) {
                    continue;
                }

                $scopeKey = $this->scopeKey($root->project_id, $root->job_site_id);

                // First sighting wins, and items are read in position order, so
                // this is the location's earliest appearance on that agenda.
                $groups[$scopeKey] ??= ['tier' => $tier, 'pos' => (int) $root->position];

                if ($item->task_id !== null && ! isset($tasks[$item->task_id])) {
                    $tasks[$item->task_id] = [
                        'tier' => $tier,
                        'root' => (int) $root->position,
                        // -1 so a parent always sorts above its own children.
                        'child' => $item->parent_id !== null ? (int) $item->position : -1,
                        'parent_task' => $item->parent_id !== null ? $root->task_id : null,
                    ];
                }
            }

            $tier = 1;
        }

        return $this->orderKeyCache[$meeting->id] = ['groups' => $groups, 'tasks' => $tasks];
    }

    /**
     * Put candidate tasks in the order they will land on the agenda.
     *
     * The picker shows this same order, so what the chair ticks is what they
     * get — previously the panel grouped by location while the button added by
     * due date, and the two disagreed.
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    public function sortCandidates(Meeting $meeting, Collection $tasks, ?array $keys = null): Collection
    {
        $keys = $keys ?? $this->orderKeys($meeting);
        $overdueFirst = $meeting->series?->putsOverdueFirst() ?? false;

        // Lateness is decided on the root line, so a parent lifts with its
        // children instead of a sub-item floating away from the line it
        // belongs under.
        $lateRoots = [];

        if ($overdueFirst) {
            foreach ($tasks as $task) {
                if ($task->isOverdue()) {
                    $lateRoots[$keys['tasks'][$task->id]['parent_task'] ?? $task->id] = true;
                }
            }
        }

        return $tasks->sortBy(function (Task $task) use ($keys, $overdueFirst, $lateRoots) {
            $key = $keys['tasks'][$task->id] ?? null;
            $group = $keys['groups'][$this->scopeKey($task->project_id, $task->job_site_id)] ?? null;
            $rootTaskId = $key['parent_task'] ?? $task->id;

            return [
                // The location block, in the order the template meeting had
                // them; a location nobody has met about yet sorts last, by name
                // so the result is at least stable.
                $group['tier'] ?? 2,
                $group['pos'] ?? 0,
                $this->scopeLabel($task->project, $task->jobSite),

                // Inside the block: the late work first when the series asks
                // for it, then last meeting's order, then anything never on an
                // agenda by due date.
                $overdueFirst && ! isset($lateRoots[$rootTaskId]) ? 1 : 0,
                $key['tier'] ?? 2,
                $key['root'] ?? 0,
                $key['child'] ?? -1,
                $task->due_date?->toDateString() ?? '9999-12-31',
                $task->id,
            ];
        })->values();
    }

    /** How a project / job site pair is keyed while ordering. */
    public function scopeKey(?int $projectId, ?int $jobSiteId): string
    {
        return $projectId === null ? 'general' : $projectId.'-'.($jobSiteId ?? 'p');
    }

    /**
     * The agenda's top-level lines cut into location blocks.
     *
     * Runs of the same location by default, so a block is what the eye sees on
     * an agenda that may still be interleaved. With `group: true` every line of
     * a location is gathered into one block, in the order the location first
     * appears — which is what tidying it does.
     *
     * @return Collection<int, array{key: string, items: Collection<int, MeetingItem>}>
     */
    protected function blocks(Meeting $meeting, bool $group = false): Collection
    {
        $roots = MeetingItem::where('meeting_id', $meeting->id)
            ->whereNull('parent_id')
            ->orderBy('position')
            ->get();

        return $this->blocksFrom($roots, $group);
    }

    /**
     * The same cut, made from lines that are already in hand.
     *
     * The agenda builder, the running minute and the ata all need the headings,
     * and all three already have their items loaded — asking the database again
     * for rows we are holding would be three extra queries for nothing.
     *
     * @param  iterable<int, MeetingItem>  $rootItems  top-level lines, in position order
     * @return Collection<int, array{key: string, label: string, project_id: ?int, job_site_id: ?int, items: Collection<int, MeetingItem>}>
     */
    public function blocksFrom(iterable $rootItems, bool $group = false): Collection
    {
        $roots = collect($rootItems);

        if ($group) {
            return $roots
                ->groupBy(fn (MeetingItem $item) => $this->scopeKey($item->project_id, $item->job_site_id))
                ->map(fn (Collection $items, string $key) => [
                    'key' => $key,
                    'label' => $items->first()->getScopeLabel(),
                    'project_id' => $items->first()->project_id,
                    'job_site_id' => $items->first()->job_site_id,
                    'items' => $items->values(),
                ])
                ->values();
        }

        $blocks = collect();

        foreach ($roots as $item) {
            $key = $this->scopeKey($item->project_id, $item->job_site_id);

            if ($blocks->isEmpty() || $blocks->last()['key'] !== $key) {
                $blocks->push([
                    'key' => $key,
                    'label' => $item->getScopeLabel(),
                    'project_id' => $item->project_id,
                    'job_site_id' => $item->job_site_id,
                    'items' => collect(),
                ]);
            }

            $blocks->last()['items']->push($item);
        }

        return $blocks;
    }

    /** Two lines belong to the same location. */
    protected function sameScope(MeetingItem $a, MeetingItem $b): bool
    {
        return $this->scopeKey($a->project_id, $a->job_site_id)
            === $this->scopeKey($b->project_id, $b->job_site_id);
    }

    /**
     * A top-level line counts as late if its own task is, or any line under it
     * is — so a parent lifts with its children instead of a sub-item floating
     * away from what it belongs under.
     */
    protected function isLate(MeetingItem $item): bool
    {
        return (bool) $item->task?->isOverdue()
            || $item->children->contains(fn (MeetingItem $child) => (bool) $child->task?->isOverdue());
    }

    /**
     * Number the top-level lines 0..n in the order given.
     *
     * Filtered on the meeting and on `parent_id` like every other position
     * write here: a re-sort must never reach a row of another meeting, and it
     * must never touch a sub-item, whose position is relative to its parent.
     *
     * @param  iterable<int, MeetingItem>  $items
     */
    protected function writeRootPositions(Meeting $meeting, iterable $items): void
    {
        $ordered = collect($items)
            ->filter(fn (MeetingItem $item) => $item->meeting_id === $meeting->id && $item->parent_id === null)
            ->values();

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $item) {
                if ((int) $item->position !== $position) {
                    $item->update(['position' => $position]);
                }
            }
        });
    }

    /** Answered once per meeting: the series behind it does not move mid-request. */
    protected array $previousIdCache = [];

    /** Every earlier meeting of the same series, cancelled ones excluded. */
    protected function previousMeetingIds(Meeting $meeting): Collection
    {
        return $this->previousIdCache[$meeting->id] ??= $this->findPreviousMeetingIds($meeting);
    }

    protected function findPreviousMeetingIds(Meeting $meeting): Collection
    {
        if (! $meeting->meeting_series_id) {
            // A one-off meeting carries forward from the meeting it was
            // explicitly chained to, if any.
            return collect(array_filter([$meeting->previous_meeting_id]));
        }

        return Meeting::where('meeting_series_id', $meeting->meeting_series_id)
            ->where('status', '!=', 'cancelled')
            ->whereKeyNot($meeting->id)
            ->where(fn (Builder $q) => $q
                ->whereDate('meeting_date', '<', $meeting->meeting_date)
                ->orWhere(fn (Builder $same) => $same
                    ->whereDate('meeting_date', '=', $meeting->meeting_date)
                    ->where('id', '<', $meeting->id)))
            ->pluck('id');
    }

    /**
     * Every earlier meeting of the series, newest first, with a count of its
     * lines so "the most recent one that has any" costs no extra query.
     *
     * @return Collection<int, Meeting>
     */
    protected function previousMeetingsNewestFirst(Meeting $meeting): Collection
    {
        $ids = $this->previousMeetingIds($meeting);

        if ($ids->isEmpty()) {
            return collect();
        }

        return Meeting::whereIn('id', $ids)
            ->withCount('allItems')
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Make room for `count` lines at the end of one location's block and
     * return the first free position.
     *
     * A location that is not on the agenda yet opens its block at the end,
     * which is how a newly added project arrives.
     *
     * Every position write here is filtered on the meeting **and** on
     * `parent_id`, never on position alone: this is the first bulk write this
     * module has on that column, and a missing clause would silently renumber
     * another meeting's agenda.
     */
    protected function openPositions(Meeting $meeting, ?int $projectId, ?int $jobSiteId, int $count): int
    {
        $block = MeetingItem::where('meeting_id', $meeting->id)
            ->whereNull('parent_id')
            ->where(fn (Builder $q) => $projectId === null
                ? $q->whereNull('project_id')
                : $q->where('project_id', $projectId))
            ->where(fn (Builder $q) => $jobSiteId === null
                ? $q->whereNull('job_site_id')
                : $q->where('job_site_id', $jobSiteId))
            ->max('position');

        if ($block === null) {
            return $this->nextPosition($meeting, null);
        }

        $at = (int) $block + 1;

        MeetingItem::where('meeting_id', $meeting->id)
            ->whereNull('parent_id')
            ->where('position', '>=', $at)
            ->increment('position', $count);

        return $at;
    }

    /**
     * The agenda line for a task, wherever it is going to sit.
     *
     * `carried_from_item_id` records the line this one continues, so the
     * minute can say "open since SITE-2026-009" — and so the next meeting can
     * read this one's shape. It points at the earlier item; it never writes to
     * it.
     *
     * @param  array<int, int>  $previousIds
     */
    protected function createTaskItem(
        Meeting $meeting,
        Task $task,
        User $actor,
        ?MeetingItem $parent,
        int $position,
        array $previousIds,
    ): MeetingItem {
        $carriedFrom = $previousIds === [] ? null : MeetingItem::where('task_id', $task->id)
            ->whereIn('meeting_id', $previousIds)
            ->orderByDesc('id')
            ->first();

        return $meeting->allItems()->create([
            'parent_id' => $parent?->id,
            'position' => $position,
            'project_id' => $task->project_id,
            'job_site_id' => $task->job_site_id,
            'type' => 'action',
            'title' => $task->title,
            'task_id' => $task->id,
            'carried_from_item_id' => $carriedFrom?->id,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Positions are 0-based, because the displayed number is position + 1.
     * Starting at 1 would number the first item on every agenda "2".
     */
    protected function nextPosition(Meeting $meeting, ?int $parentId): int
    {
        $highest = MeetingItem::where('meeting_id', $meeting->id)
            ->where('parent_id', $parentId)
            ->max('position');

        return $highest === null ? 0 : (int) $highest + 1;
    }

    /** Close the gaps a removal leaves, so positions stay 0..n. */
    protected function resequence(int $meetingId, ?int $parentId): void
    {
        MeetingItem::where('meeting_id', $meetingId)
            ->where('parent_id', $parentId)
            ->orderBy('position')
            ->get()
            ->each(fn (MeetingItem $item, int $index) => $item->update(['position' => $index]));
    }

    /**
     * A parent line, proved to be a top-level line of *this* meeting.
     *
     * `parent_id` arrives from the browser, and nothing else checked it. A
     * crafted id could hang a line of this agenda off a line of a previous —
     * even published — meeting, which would show up as a new sub-item on that
     * meeting's minute. An id pointing at a sub-item would build a third level
     * the module does not have.
     */
    protected function assertOwnParent(Meeting $meeting, ?int $parentId): ?int
    {
        if ($parentId === null) {
            return null;
        }

        $parent = MeetingItem::where('meeting_id', $meeting->id)
            ->whereNull('parent_id')
            ->find($parentId);

        abort_if($parent === null, 404, __('That item is not on this agenda.'));

        return $parent->id;
    }

    /** A published minute is a record; its agenda is closed. */
    protected function assertBuildable(Meeting $meeting): void
    {
        abort_unless($meeting->isDraft(), 403, 'This minute is published and its agenda can no longer be changed.');
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    /**
     * "Open since SITE-2026-009 · 3 meetings" — how long an item has been
     * dragging, which is the thing a chair actually wants to know.
     */
    public function history(Task $task): array
    {
        // The panel asks this once per proposed row. When the caller has
        // already loaded the history — which `carryForwardCandidates()` does —
        // reading it costs nothing instead of a query a row.
        $first = $task->relationLoaded('meetingItems')
            ? $task->meetingItems->sortBy('id')->first()
            : $task->meetingItems()->with('meeting')->orderBy('id')->first();

        return [
            'first_meeting' => $first?->meeting?->number,
            'meetings' => $task->meeting_items_count ?? $task->meetingItems()->count(),
        ];
    }

    public function scopeLabel(?Project $project, ?JobSite $jobSite): string
    {
        if (! $project) {
            return __('General');
        }

        return $jobSite
            ? $project->project_name.' › '.$jobSite->job_site_name
            : $project->project_name;
    }
}
