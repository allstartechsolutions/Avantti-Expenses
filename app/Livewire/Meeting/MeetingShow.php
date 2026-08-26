<?php

namespace App\Livewire\Meeting;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesTasks;
use App\Livewire\Concerns\RaisesAgendaItems;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Services\MeetingMinuteDistributor;
use App\Services\MeetingService;
use App\Services\TaskService;
use App\Support\RichText;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The meeting itself: the working surface while it is a draft, and the minute
 * once it is published.
 *
 * One screen for both on purpose — what the chair typed during the meeting is
 * exactly what the record says afterwards, and nobody has to wonder whether a
 * separate "minute view" shows the same thing.
 *
 * See docs/meetings-module-plan.md §5.3.
 */
class MeetingShow extends Component
{
    use AuthorizesAbility;

    use ManagesTasks, RaisesAgendaItems;

    public Meeting $meeting;

    /** Per-item text, edited live: item id => discussion / decision. */
    public array $discussion = [];
    public array $decision = [];

    /**
     * The note being written against one item's task, keyed by item id.
     *
     * Keyed on purpose: a single shared property binds every note box on the
     * page to the same value, so typing against one item types against all of
     * them.
     */
    public array $itemNote = [];

    public string $summary = '';
    public string $nextMeetingDate = '';

    /** Correcting a published minute: captured before anything may be edited. */
    public bool $revising = false;
    public string $revisionReason = '';
    public array $revisionBaseline = [];

    /** The cancel prompt. */
    public string $cancelReason = '';

    /**
     * How many of a task's notes the minute shows before offering the rest.
     *
     * The eager load and the partial read the same number, so the "see all"
     * link appears exactly when something is being held back.
     */
    public const NOTES_SHOWN = 4;

    public function mount(Meeting $meeting): void
    {
        $this->authorizeAbility('meetings.view');

        $this->meeting = $meeting->load(self::HEADER_RELATIONS);

        $this->fillEditableText();
        $this->summary = (string) $meeting->summary;
        $this->nextMeetingDate = $meeting->next_meeting_date?->toDateString() ?? '';
    }

    /**
     * Everything the page frame reads, in one go.
     *
     * The corrections panel, the "filed as" line and the links to the meeting
     * either side were each being fetched a row at a time as the Blade reached
     * them.
     */
    protected const HEADER_RELATIONS = [
        'series', 'chair', 'secretary', 'attendees.user', 'publishedBy', 'cancelledBy',
        'revisions.revisedBy', 'document', 'previousMeeting', 'nextMeeting',
    ];

    protected function fillEditableText(): void
    {
        // Read from the collection the page is about to render rather than
        // fetching the same rows again for their ids alone.
        foreach ($this->flatItems() as $item) {
            $this->discussion[$item->id] = (string) $item->discussion;
            $this->decision[$item->id] = (string) $item->decision;
            $this->itemNote[$item->id] = '';
        }
    }

    protected function meetings(): MeetingService
    {
        return app(MeetingService::class);
    }

    // =========================================================================
    // STATE
    // =========================================================================

    /** Whether the text on this screen may be typed into right now. */
    public function isEditable(): bool
    {
        if ($this->meeting->isCancelled()) {
            return false;
        }

        return $this->meeting->isDraft()
            ? $this->meeting->canEdit(auth()->user())
            : $this->revising;
    }

    #[Computed]
    public function items(): Collection
    {
        $roots = $this->meeting->items()
            ->with([
                'task.owner', 'task.assignees', 'task.subtasks',
                // The guards on a task ask the permission resolver which
                // project it belongs to; without these it goes and finds the
                // project itself, once per task, before its own cache can help.
                'task.project', 'task.jobSite',
                // The panel shows the newest few and says how many there are,
                // so only that many are read — a task carried through twenty
                // meetings was dragging every note it had ever collected onto
                // the screen to display four. The total comes from the count.
                'task' => fn ($query) => $query->withCount('notes'),
                'task.notes' => fn ($query) => $query->limit(self::NOTES_SHOWN),
                'task.notes.user', 'task.notes.meeting',
                'project', 'jobSite', 'carriedFrom.meeting',
                'children.task.owner', 'children.project', 'children.jobSite', 'children.carriedFrom.meeting',
            ])
            ->get();

        return MeetingItem::linkParents($roots);
    }

    /** Every line of the agenda, sub-items included, from what is already loaded. */
    protected function flatItems(): Collection
    {
        return $this->items->flatMap(fn (MeetingItem $item) => collect([$item])->concat($item->children));
    }

    /** The minute cut into location blocks, so it reads the way the agenda did. */
    #[Computed]
    public function itemBlocks(): Collection
    {
        return app(\App\Services\MeetingAgendaService::class)->blocksFrom($this->items);
    }

    #[Computed]
    public function counters(): array
    {
        return $this->meetings()->counters($this->meeting, $this->flatItems());
    }

    /** Attendees with a usable address — who the minute will actually reach. */
    #[Computed]
    public function minuteRecipients(): Collection
    {
        return app(MeetingMinuteDistributor::class)->recipients($this->meeting);
    }

    /**
     * Later meetings of this series that already have an agenda.
     *
     * Publishing now photographs the tasks as they stand *today*, which is
     * after those meetings were prepared — so the figures this minute keeps are
     * later than its own date. Allowed, but said out loud.
     */
    #[Computed]
    public function laterMeetings(): Collection
    {
        return app(\App\Services\MeetingAgendaService::class)->laterMeetingsWithItems($this->meeting);
    }

    #[Computed]
    public function unownedActions(): Collection
    {
        return $this->meetings()->unownedActionItems($this->meeting, $this->flatItems());
    }

    // =========================================================================
    // RUNNING THE MEETING
    // =========================================================================

    /** Saved as it is typed, so nothing is lost if the laptop closes. */
    public function updatedDiscussion($value, string $key): void
    {
        $this->saveItemText((int) $key, ['discussion' => $value]);
    }

    public function updatedDecision($value, string $key): void
    {
        $this->saveItemText((int) $key, ['decision' => $value]);
    }

    protected function saveItemText(int $itemId, array $data): void
    {
        abort_unless($this->isEditable(), 403);

        $item = MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($itemId);

        $item->update($data);

        unset($this->items, $this->counters);
    }

    /**
     * An item that was on the agenda but never reached. It rolls to the next
     * meeting untouched rather than being recorded as discussed.
     */
    public function toggleDiscussed(int $itemId): void
    {
        abort_unless($this->isEditable(), 403);

        $item = MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($itemId);
        $item->update(['discussed' => ! $item->discussed]);

        unset($this->items, $this->counters);
    }

    public function setAttendance(int $attendeeId, string $attendance): void
    {
        abort_unless($this->isEditable() && in_array($attendance, ['present', 'absent', 'excused', ''], true), 403);

        // Pressing the marked one again clears it, so a mis-click can be undone
        // rather than leaving the record asserting something.
        $this->meeting->attendees()->findOrFail($attendeeId)
            ->update(['attendance' => $attendance ?: null]);

        $this->meeting->load('attendees.user');
        unset($this->counters);
    }

    public function saveSummary(): void
    {
        abort_unless($this->isEditable(), 403);

        // Editor output is never trusted: only the tags the toolbar can make
        // survive, and no attributes beyond a checked href.
        $this->summary = RichText::sanitize($this->summary);

        $this->meeting->update([
            'summary' => RichText::isEmpty($this->summary) ? null : $this->summary,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('message', __('Saved.'));
    }

    /**
     * A note recorded during the meeting is stamped with it, so the task and
     * the minute can never tell different stories.
     */
    public function addMeetingNote(int $itemId, int $taskId, TaskService $tasks): void
    {
        abort_unless($this->isEditable(), 403);

        $body = trim($this->itemNote[$itemId] ?? '');

        if ($body === '') {
            return;
        }

        $this->runTaskAction(fn () => $tasks->addNote(
            \App\Models\Task::findOrFail($taskId),
            auth()->user(),
            $body,
            $this->meeting
        ));

        // Only this item's box is cleared; the others were never touched.
        $this->itemNote[$itemId] = '';

        unset($this->items);
    }

    /** The chair closing an item in front of everyone. */
    public function confirmFromMeeting(int $taskId, TaskService $tasks): void
    {
        abort_unless($this->isEditable(), 403);

        $this->runTaskAction(
            fn () => $tasks->confirmCompletion(\App\Models\Task::findOrFail($taskId), auth()->user(), $this->meeting),
            __('Task completed.')
        );

        unset($this->items, $this->counters);
    }

    public function setProgressFromMeeting(int $taskId, int $progress, TaskService $tasks): void
    {
        abort_unless($this->isEditable(), 403);

        $this->runTaskAction(fn () => $tasks->setProgress(
            \App\Models\Task::findOrFail($taskId),
            $progress,
            auth()->user(),
            $this->meeting
        ));

        unset($this->items, $this->counters);
    }

    /** Things come up mid-meeting; the agenda is not frozen until publication. */
    protected function afterItemRaised(MeetingItem $item): void
    {
        unset($this->items, $this->counters, $this->unownedActions);

        $this->discussion[$item->id] = '';
        $this->decision[$item->id] = '';
        $this->itemNote[$item->id] = '';
    }

    public function removeAgendaItem(int $itemId, \App\Services\MeetingAgendaService $agenda): void
    {
        abort_unless($this->meeting->isDraft() && $this->meeting->canEdit(auth()->user()), 403);

        $agenda->removeItem(MeetingItem::where('meeting_id', $this->meeting->id)->findOrFail($itemId));

        unset($this->items, $this->counters, $this->unownedActions);

        session()->flash('message', __('Taken off the agenda. The task itself is untouched and stays open.'));
    }

    // =========================================================================
    // PUBLISHING
    // =========================================================================

    public function publish(): void
    {
        // Publishing freezes the minute and mails it to every attendee. It had
        // no guard of any kind.
        $this->authorizeAbility('meetings.freeze');

        try {
            $this->meetings()->publish($this->meeting, auth()->user());
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        if ($this->nextMeetingDate) {
            $this->meeting->update(['next_meeting_date' => $this->nextMeetingDate]);
        }

        $this->meeting = $this->meeting->fresh(self::HEADER_RELATIONS);
        unset($this->items, $this->counters, $this->unownedActions);

        $this->dispatch('close-modal', 'publish-modal');

        // Keeping, filing and sending happen after the publication, and after
        // the response: a slow mail server must not make publishing feel slow,
        // and a failure there must not undo a publication that happened.
        $meeting = $this->meeting;
        $actor = auth()->user();

        dispatch(function () use ($meeting, $actor) {
            app(MeetingService::class)->distribute($meeting, $actor);
        })->afterResponse();

        session()->flash('message', __('Minute :number published. It is being filed and sent to the attendees.', [
            'number' => $this->meeting->number,
        ]));
    }

    /**
     * Send it again — an address was wrong, somebody was added, or the mail
     * server was down when it was published.
     */
    public function resendMinute(MeetingMinuteDistributor $distributor): void
    {
        abort_unless($this->meeting->isPublished(), 403);
        // The chair may always resend their own minute; anybody else needs
        // the grant that publishes one.
        abort_unless(
            $this->meeting->chair_id === auth()->id()
                || $this->allowsAbility('meetings.freeze'),
            403,
            __('You do not have permission to do that.'),
        );

        $result = $distributor->distribute($this->meeting, auth()->user());

        $this->meeting = $this->meeting->fresh(self::HEADER_RELATIONS);

        session()->flash('message', $result['failed'] > 0
            ? __('Sent to :sent attendee(s); :failed could not be reached — check the log for the addresses.', [
                'sent' => $result['sent'], 'failed' => $result['failed'],
            ])
            : trans_choice('Sent to :count attendee.|Sent to :count attendees.', $result['sent'], ['count' => $result['sent']]));
    }

    public function createFollowUp()
    {
        try {
            $next = $this->meetings()->createFollowUp($this->meeting, auth()->user(), $this->nextMeetingDate ?: null);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return null;
        }

        session()->flash('message', __('Meeting :number created, with everything still open already on its agenda.', [
            'number' => $next->number,
        ]));

        return $this->redirect(route('meetings.agenda', $next), navigate: true);
    }

    // =========================================================================
    // CORRECTING AND CANCELLING
    // =========================================================================

    public function startRevision(): void
    {
        abort_unless($this->meeting->canRevise(auth()->user()), 403);

        if (trim($this->revisionReason) === '') {
            $this->addError('revisionReason', __('Say what is being corrected and why.'));

            return;
        }

        // Remember what the record said, so the revision can show before and
        // after rather than just "something changed".
        $this->revisionBaseline = [
            'summary' => (string) $this->meeting->summary,
            'discussion' => $this->discussion,
            'decision' => $this->decision,
        ];

        $this->revising = true;
        $this->dispatch('close-modal', 'revision-modal');
    }

    public function saveRevision(): void
    {
        abort_unless($this->revising, 403);

        $changes = [];

        if ($this->summary !== $this->revisionBaseline['summary']) {
            $changes['summary'] = ['from' => $this->revisionBaseline['summary'], 'to' => $this->summary];
        }

        foreach (['discussion', 'decision'] as $field) {
            foreach ($this->{$field} as $itemId => $value) {
                $was = $this->revisionBaseline[$field][$itemId] ?? '';

                if ((string) $value !== (string) $was) {
                    $item = MeetingItem::find($itemId);
                    $changes[$field.'.'.$itemId] = [
                        'item' => $item?->number(),
                        'from' => $was,
                        'to' => (string) $value,
                    ];
                }
            }
        }

        try {
            $this->summary = RichText::sanitize($this->summary);
            $this->meeting->update(['summary' => RichText::isEmpty($this->summary) ? null : $this->summary]);
            $this->meetings()->recordRevision($this->meeting, auth()->user(), $this->revisionReason, $changes);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->revising = false;
        $this->revisionReason = '';
        $this->revisionBaseline = [];
        $this->meeting = $this->meeting->fresh(self::HEADER_RELATIONS);

        session()->flash('message', __('Correction recorded. It is shown on the minute.'));
    }

    public function cancelRevision(): void
    {
        // Put back what the record said before the correction was started.
        $this->summary = $this->revisionBaseline['summary'] ?? $this->summary;

        foreach (['discussion', 'decision'] as $field) {
            foreach ($this->revisionBaseline[$field] ?? [] as $itemId => $value) {
                if ((string) ($this->{$field}[$itemId] ?? '') !== (string) $value) {
                    MeetingItem::where('id', $itemId)->update([$field => $value ?: null]);
                }

                $this->{$field}[$itemId] = $value;
            }
        }

        $this->revising = false;
        $this->revisionReason = '';
        $this->revisionBaseline = [];

        unset($this->items);
    }

    public function cancelMeeting(): void
    {
        try {
            $this->meetings()->cancel($this->meeting, auth()->user(), $this->cancelReason);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->cancelReason = '';
        $this->meeting = $this->meeting->fresh(self::HEADER_RELATIONS);

        $this->dispatch('close-modal', 'cancel-meeting-modal');

        session()->flash('message', __('Meeting cancelled.'));
    }

    /**
     * Delete a meeting that never became a record.
     *
     * For the one case cancelling does not cover: a meeting created by mistake.
     * A published minute is refused — it has been filed and mailed, so it is
     * corrected or the meeting is cancelled, never removed.
     */
    public function deleteMeeting()
    {
        try {
            $this->meetings()->delete($this->meeting, auth()->user());
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return null;
        }

        session()->flash('message', __('Meeting :number deleted. The tasks it discussed are untouched and stay open.', [
            'number' => $this->meeting->number,
        ]));

        return $this->redirect(route('meetings.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.meeting.meeting-show')->layout('components.layouts.app');
    }
}
