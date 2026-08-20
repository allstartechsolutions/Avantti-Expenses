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

        return Task::query()
            ->open()
            ->whereHas('meetingItems', fn (Builder $q) => $q->whereIn('meeting_id', $previousMeetings))
            ->whereDoesntHave('meetingItems', fn (Builder $q) => $q->where('meeting_id', $meeting->id))
            ->with(['project', 'jobSite', 'owner', 'notes' => fn ($q) => $q->limit(1)])
            ->withCount('meetingItems')
            ->orderByRaw('due_date is null, due_date asc')
            ->get();
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
            ->withCount('meetingItems')
            ->orderByRaw('due_date is null, due_date asc');

        return [
            'tracked' => $query()->meetingTracked()->get(),
            'direct' => $query()->direct()->get(),
        ];
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
     */
    public function addTask(Meeting $meeting, Task $task, User $actor): MeetingItem
    {
        $this->assertBuildable($meeting);

        // Already there — adding it twice would give the same task two lines.
        if ($existing = $meeting->allItems()->where('task_id', $task->id)->first()) {
            return $existing;
        }

        $carriedFrom = MeetingItem::where('task_id', $task->id)
            ->whereIn('meeting_id', $this->previousMeetingIds($meeting))
            ->orderByDesc('id')
            ->first();

        return $meeting->allItems()->create([
            'position' => $this->nextPosition($meeting, null),
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
     * A line raised fresh at this meeting. An action item may carry a task
     * created with it — that is the normal way work leaves a meeting.
     */
    public function addItem(Meeting $meeting, array $data, User $actor, ?Task $task = null): MeetingItem
    {
        $this->assertBuildable($meeting);

        $parentId = $data['parent_id'] ?? null;

        return $meeting->allItems()->create([
            'parent_id' => $parentId,
            'position' => $this->nextPosition($meeting, $parentId),
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
     * Move a line up or down among its siblings. The displayed number is
     * computed from position, so this is all reordering needs to touch.
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

        DB::transaction(function () use ($item, $other) {
            $itemPosition = $item->position;

            $item->update(['position' => $other->position]);
            $other->update(['position' => $itemPosition]);
        });
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
            ->pluck('id')
            ->all();

        $ordered = collect($orderedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $siblings, true))
            ->unique()
            ->values();

        // Anything the browser left out keeps its place at the end, so a stale
        // page cannot silently drop a line off the agenda.
        $missing = collect($siblings)->reject(fn (int $id) => $ordered->contains($id));
        $final = $ordered->concat($missing);

        DB::transaction(function () use ($final) {
            foreach ($final as $position => $id) {
                MeetingItem::whereKey($id)->update(['position' => $position]);
            }
        });
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /** Every earlier meeting of the same series, cancelled ones excluded. */
    protected function previousMeetingIds(Meeting $meeting): Collection
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
        $first = $task->meetingItems()->with('meeting')->orderBy('id')->first();

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
