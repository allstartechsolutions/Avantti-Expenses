<?php

namespace App\Livewire\Meeting;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\MeetingSeries;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Creating a meeting, and correcting one that has not been published.
 *
 * Everything a series knows is copied in the moment the series is chosen —
 * the register, the location, the next date — because the point of a series is
 * that nobody retypes it every week.
 *
 * The agenda is built on the meeting itself (phase 4); this screen settles
 * when it is, where it is, and who is in the room.
 */
class MeetingForm extends Component
{
    use AuthorizesAbility;

    public ?Meeting $meeting = null;

    public string $meeting_series_id = '';
    public string $title = '';
    public string $meeting_date = '';
    public string $started_at = '';
    public string $ended_at = '';
    public string $location = '';
    public string $meeting_url = '';
    public string $chair_id = '';
    public string $secretary_id = '';

    /**
     * @var array<int, array{id:?int, user_id:string, name:string, company:string,
     *                       email:string, role:string, attendance:string}>
     */
    public array $attendees = [];

    public function mount(?Meeting $meeting = null): void
    {
        // A meeting has no project of its own — it spans several through its
        // items — so its grants are asked without a scope. For somebody
        // confined to particular projects the resolver answers from their
        // memberships taken together, which is what a cross-project screen
        // needs.
        $this->authorizeAbility($meeting?->exists ? 'meetings.edit' : 'meetings.create');

        if ($meeting?->exists) {
            abort_unless($meeting->isDraft(), 403, 'A published minute is corrected through a revision, not the form.');

            $this->meeting = $meeting->load('attendees.user');
            $this->fillFromMeeting();

            return;
        }

        $this->meeting_date = now()->toDateString();
        $this->chair_id = (string) auth()->id();
        $this->secretary_id = (string) auth()->id();
    }

    protected function fillFromMeeting(): void
    {
        $this->meeting_series_id = (string) ($this->meeting->meeting_series_id ?? '');
        $this->title = $this->meeting->title;
        $this->meeting_date = $this->meeting->meeting_date->toDateString();
        $this->started_at = $this->meeting->started_at ? substr($this->meeting->started_at, 0, 5) : '';
        $this->ended_at = $this->meeting->ended_at ? substr($this->meeting->ended_at, 0, 5) : '';
        $this->location = (string) $this->meeting->location;
        $this->meeting_url = (string) $this->meeting->meeting_url;
        $this->chair_id = (string) ($this->meeting->chair_id ?? '');
        $this->secretary_id = (string) ($this->meeting->secretary_id ?? '');

        $this->attendees = $this->meeting->attendees->map(fn ($a) => [
            'id' => $a->id,
            'user_id' => $a->user_id ? (string) $a->user_id : '',
            'name' => (string) $a->name,
            'company' => (string) $a->company,
            'email' => (string) $a->email,
            'role' => $a->role,
            'attendance' => $a->attendance,
        ])->all();
    }

    // =========================================================================
    // LOOKUPS
    // =========================================================================

    #[Computed]
    public function seriesOptions(): Collection
    {
        return MeetingSeries::active()->orderBy('name')->get(['id', 'name', 'code', 'cadence', 'default_location']);
    }

    #[Computed]
    public function users(): Collection
    {
        return User::where('status', UserStatus::ACTIVE)->orderBy('name')->get(['id', 'name']);
    }

    /** What this meeting's number will be, shown before it is issued. */
    #[Computed]
    public function numberPreview(): string
    {
        if ($this->meeting?->exists) {
            return $this->meeting->number;
        }

        $series = $this->meeting_series_id ? MeetingSeries::find($this->meeting_series_id) : null;
        $year = $this->meeting_date ? Carbon::parse($this->meeting_date)->year : now()->year;

        return Meeting::nextNumber($series, $year);
    }

    /** The meeting whose open items this one will carry forward (phase 4). */
    #[Computed]
    public function previousMeeting(): ?Meeting
    {
        if (! $this->meeting_series_id) {
            return null;
        }

        return Meeting::where('meeting_series_id', $this->meeting_series_id)
            ->where('status', '!=', 'cancelled')
            ->when($this->meeting?->exists, fn ($q) => $q->whereKeyNot($this->meeting->id))
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->first();
    }

    // =========================================================================
    // THE SERIES DOES THE TYPING
    // =========================================================================

    public function updatedMeetingSeriesId(): void
    {
        $series = $this->meeting_series_id ? MeetingSeries::with('members.user')->find($this->meeting_series_id) : null;

        if (! $series) {
            return;
        }

        if ($this->title === '' || ! $this->meeting?->exists) {
            $this->title = $series->name;
        }

        if ($this->location === '') {
            $this->location = (string) $series->default_location;
        }

        // Only fill an empty register: nobody wants their corrections thrown
        // away because they touched the series select again.
        if (empty($this->attendees)) {
            $this->attendees = $series->members->map(fn ($m) => [
                'id' => null,
                'user_id' => $m->user_id ? (string) $m->user_id : '',
                'name' => (string) $m->name,
                'company' => (string) $m->company,
                'email' => (string) $m->email,
                'role' => $m->role,
                // Blank until somebody says otherwise on the day.
                'attendance' => '',
            ])->all();

            $chair = $series->members->firstWhere('role', 'chair');

            if ($chair?->user_id) {
                $this->chair_id = (string) $chair->user_id;
            }

            $secretary = $series->members->firstWhere('role', 'secretary');

            if ($secretary?->user_id) {
                $this->secretary_id = (string) $secretary->user_id;
            }
        }

        // A cadence suggests the date *after the last meeting* — for the first
        // meeting of a series there is nothing to count from, and pushing it a
        // week out would be a guess the user has to undo.
        if (! $this->meeting?->exists && $series->latestMeeting() && $suggested = $series->suggestNextDate()) {
            $this->meeting_date = $suggested->toDateString();
        }
    }

    public function addAttendee(): void
    {
        $this->attendees[] = [
            'id' => null,
            'user_id' => '',
            'name' => '',
            'company' => '',
            'email' => '',
            'role' => 'participant',
            'attendance' => '',
        ];
    }

    public function removeAttendee(int $index): void
    {
        unset($this->attendees[$index]);
        $this->attendees = array_values($this->attendees);
    }

    /** Bring the register back from the series after it has been emptied. */
    public function loadSeriesAttendees(): void
    {
        $this->attendees = [];
        $this->updatedMeetingSeriesId();
    }

    // =========================================================================
    // SAVING
    // =========================================================================

    public function save()
    {
        $this->validate([
            'meeting_series_id' => ['nullable', 'integer', 'exists:meeting_series,id'],
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'started_at' => ['nullable', 'date_format:H:i'],
            'ended_at' => ['nullable', 'date_format:H:i', 'after:started_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:255'],
            'chair_id' => ['required', 'integer', 'exists:users,id'],
            'secretary_id' => ['nullable', 'integer', 'exists:users,id'],
            'attendees' => ['array'],
            'attendees.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attendees.*.name' => ['nullable', 'string', 'max:255'],
            'attendees.*.company' => ['nullable', 'string', 'max:255'],
            'attendees.*.email' => ['nullable', 'email', 'max:255'],
            'attendees.*.role' => ['required', 'in:chair,secretary,participant'],
            'attendees.*.attendance' => ['nullable', 'in:present,absent,excused'],
        ], [
            'ended_at.after' => __('The meeting cannot end before it starts.'),
        ]);

        foreach ($this->attendees as $index => $attendee) {
            if (! $attendee['user_id'] && trim($attendee['name']) === '') {
                $this->addError("attendees.{$index}.name", __('Choose a user, or type a name for someone outside the company.'));

                return;
            }
        }

        $meeting = DB::transaction(function () {
            $data = [
                'meeting_series_id' => $this->meeting_series_id ?: null,
                'title' => $this->title,
                'meeting_date' => $this->meeting_date,
                'started_at' => $this->started_at ?: null,
                'ended_at' => $this->ended_at ?: null,
                'location' => $this->location ?: null,
                'meeting_url' => $this->meeting_url ?: null,
                'chair_id' => $this->chair_id,
                'secretary_id' => $this->secretary_id ?: null,
                'updated_by' => auth()->id(),
            ];

            if ($this->meeting?->exists) {
                $this->meeting->update($data);
                $meeting = $this->meeting;
            } else {
                $series = $this->meeting_series_id ? MeetingSeries::find($this->meeting_series_id) : null;

                $meeting = Meeting::create($data + [
                    // Issued inside the transaction: two people creating a
                    // meeting at once must not take the same number.
                    'number' => Meeting::nextNumber($series, Carbon::parse($this->meeting_date)->year),
                    'status' => 'draft',
                    'previous_meeting_id' => $this->previousMeeting?->id,
                    'created_by' => auth()->id(),
                ]);
            }

            // The register is small and rewritten wholesale; keeping the ids
            // would buy nothing and lose the ordering.
            $meeting->attendees()->delete();

            foreach ($this->attendees as $attendee) {
                $meeting->attendees()->create([
                    'user_id' => $attendee['user_id'] ?: null,
                    'name' => $attendee['user_id'] ? null : $attendee['name'],
                    'company' => $attendee['user_id'] ? null : ($attendee['company'] ?: null),
                    'email' => $attendee['user_id'] ? null : ($attendee['email'] ?: null),
                    'role' => $attendee['role'],
                    'attendance' => $attendee['attendance'] ?: null,
                ]);
            }

            return $meeting;
        });

        session()->flash('message', $this->meeting?->wasRecentlyCreated === false && $this->meeting?->exists
            ? __('Meeting updated.')
            : __('Meeting :number created.', ['number' => $meeting->number]));

        return $this->redirect(route('meetings.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.meeting.meeting-form')->layout('components.layouts.app');
    }
}
