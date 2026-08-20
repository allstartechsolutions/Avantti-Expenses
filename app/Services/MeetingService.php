<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * What happens to a meeting as a whole: publishing it, correcting it after the
 * fact, cancelling it, and setting up the one that follows.
 *
 * Publishing is the moment a working document becomes a record, so it is the
 * moment everything gets checked and frozen. See docs/meetings-module-plan.md
 * §5.3.
 */
class MeetingService
{
    public function __construct(private readonly MeetingAgendaService $agenda)
    {
    }

    // =========================================================================
    // PUBLISHING
    // =========================================================================

    /**
     * Action items with nobody's name against them or no date.
     *
     * A minute that says "we will fix the drainage" and names no one and no
     * date has promised nothing, so publishing stops here.
     *
     * @return Collection<int, MeetingItem>
     */
    public function unownedActionItems(Meeting $meeting): Collection
    {
        return $meeting->allItems()
            ->where('type', 'action')
            ->with('task')
            ->get()
            ->filter(fn (MeetingItem $item) => $item->task === null
                || $item->task->owner_id === null
                || $item->task->due_date === null);
    }

    public function publish(Meeting $meeting, User $actor): Meeting
    {
        if (! $meeting->canPublish($actor)) {
            throw new RuntimeException(__('You may not publish this minute.'));
        }

        if ($meeting->allItems()->doesntExist()) {
            throw new RuntimeException(__('There is nothing on the agenda to publish.'));
        }

        $unowned = $this->unownedActionItems($meeting);

        if ($unowned->isNotEmpty()) {
            throw new RuntimeException(trans_choice(
                'Item :items has no owner or no date. Every action item needs both before the minute is published.|Items :items have no owner or no date. Every action item needs both before the minute is published.',
                $unowned->count(),
                ['items' => $unowned->map(fn (MeetingItem $item) => $item->number())->implode(', ')]
            ));
        }

        return DB::transaction(function () use ($meeting, $actor) {
            // Freeze how every task stood today. The minute has to keep saying
            // 60% next month when the task has moved on to 90%.
            foreach ($meeting->allItems()->with('task.owner')->get() as $item) {
                $item->update(['status_at_meeting' => $item->snapshotTask()]);
            }

            $meeting->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return $meeting->fresh();
        });
    }

    /**
     * Keep, file and send the minute.
     *
     * Deliberately after the transaction that publishes: rendering a PDF and
     * talking to a mail server are slow and can fail, and neither may undo a
     * publication that already happened. Whatever fails here can be retried
     * from the screen.
     *
     * @return array{stored:bool, filed:bool, sent:int, failed:int}
     */
    public function distribute(Meeting $meeting, User $actor): array
    {
        return app(MeetingMinuteDistributor::class)->distribute($meeting, $actor);
    }

    // =========================================================================
    // THE MEETING THAT FOLLOWS
    // =========================================================================

    /**
     * Create the next meeting of the series as a draft, with its register and
     * everything still open already on it.
     *
     * Nothing is scheduled automatically anywhere in this module; this only
     * runs because somebody pressed the button.
     */
    public function createFollowUp(Meeting $meeting, User $actor, ?string $date = null): Meeting
    {
        abort_unless($actor->is_admin || $actor->is_manager, 403);

        if ($meeting->next_meeting_id && $existing = Meeting::find($meeting->next_meeting_id)) {
            return $existing;
        }

        $date = $date
            ?: $meeting->next_meeting_date?->toDateString()
            ?: $meeting->series?->suggestNextDate($meeting->meeting_date)?->toDateString()
            ?: $meeting->meeting_date->copy()->addWeek()->toDateString();

        return DB::transaction(function () use ($meeting, $actor, $date) {
            $next = Meeting::create([
                'meeting_series_id' => $meeting->meeting_series_id,
                'number' => Meeting::nextNumber($meeting->series, Carbon::parse($date)->year),
                'title' => $meeting->title,
                'meeting_date' => $date,
                'started_at' => $meeting->started_at,
                'location' => $meeting->location,
                'meeting_url' => $meeting->meeting_url,
                'chair_id' => $meeting->chair_id,
                'secretary_id' => $meeting->secretary_id,
                'status' => 'draft',
                'previous_meeting_id' => $meeting->id,
                'created_by' => $actor->id,
            ]);

            // The same room, with everyone marked present until the day says
            // otherwise.
            foreach ($meeting->attendees as $attendee) {
                $next->attendees()->create([
                    'user_id' => $attendee->user_id,
                    'name' => $attendee->name,
                    'company' => $attendee->company,
                    'email' => $attendee->email,
                    'role' => $attendee->role,
                    // The register comes across; who turned up does not.
                    'attendance' => null,
                ]);
            }

            $meeting->update(['next_meeting_id' => $next->id, 'next_meeting_date' => $date]);

            // Everything still open comes across, so the follow-up opens with
            // the work rather than an empty page.
            foreach ($this->agenda->carryForwardCandidates($next) as $task) {
                $this->agenda->addTask($next, $task, $actor);
            }

            return $next->fresh();
        });
    }

    // =========================================================================
    // CORRECTING A PUBLISHED MINUTE
    // =========================================================================

    /**
     * Record a correction made after publication.
     *
     * The record itself is allowed to change — people mishear and mistype —
     * but never quietly: the reason and the before/after are kept and shown on
     * the document.
     *
     * @param  array<string, array{from:mixed, to:mixed}>  $changes
     */
    public function recordRevision(Meeting $meeting, User $actor, string $reason, array $changes): void
    {
        if (! $meeting->canRevise($actor)) {
            throw new RuntimeException(__('Only an administrator may correct a published minute.'));
        }

        if (trim($reason) === '') {
            throw new RuntimeException(__('Say what is being corrected and why.'));
        }

        if (empty($changes)) {
            throw new RuntimeException(__('Nothing was changed.'));
        }

        DB::transaction(function () use ($meeting, $actor, $reason, $changes) {
            $meeting->revisions()->create([
                'revision_number' => (int) $meeting->revisions()->max('revision_number') + 1,
                'revised_by' => $actor->id,
                'reason' => $reason,
                'changes' => $changes,
            ]);

            $meeting->update(['updated_by' => $actor->id]);
        });
    }

    // =========================================================================
    // CANCELLING
    // =========================================================================

    public function cancel(Meeting $meeting, User $actor, string $reason): Meeting
    {
        if (! $meeting->canCancel($actor)) {
            throw new RuntimeException(__('You may not cancel this meeting.'));
        }

        if (trim($reason) === '') {
            throw new RuntimeException(__('Say why the meeting is being cancelled.'));
        }

        return DB::transaction(function () use ($meeting, $actor, $reason) {
            $meeting->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            return $meeting->fresh();
        });
    }

    // =========================================================================
    // WHAT THE MEETING IS DOING RIGHT NOW
    // =========================================================================

    /**
     * The live counters the chair reads while the meeting runs.
     */
    public function counters(Meeting $meeting): array
    {
        $items = $meeting->allItems()->with('task')->get();
        $actions = $items->where('type', 'action');

        return [
            'items' => $items->count(),
            'discussed' => $items->where('discussed', true)->filter(fn (MeetingItem $i) => filled($i->discussion))->count(),
            'decisions' => $items->filter(fn (MeetingItem $i) => filled($i->decision))->count(),
            'actions' => $actions->count(),
            'raised_here' => $items->filter(fn (MeetingItem $i) => $i->task && $i->task->origin_meeting_id === $meeting->id)->count(),
            'closed_here' => $actions->filter(fn (MeetingItem $i) => $i->task
                && $i->task->status === 'completed'
                && $i->task->completed_at?->gte($meeting->created_at))->count(),
            'overdue' => $actions->filter(fn (MeetingItem $i) => $i->task?->isOverdue())->count(),
            'awaiting' => $actions->filter(fn (MeetingItem $i) => $i->task?->status === 'ready')->count(),
            'present' => $meeting->attendees->where('attendance', 'present')->count(),
            'invited' => $meeting->attendees->count(),
            'unmarked' => $meeting->attendees->whereNull('attendance')->count(),
        ];
    }
}
