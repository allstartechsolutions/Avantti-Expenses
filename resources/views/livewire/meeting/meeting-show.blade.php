<div>
    @php
        $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
        $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
        $stampFormat = config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A';
        $counters = $this->counters;
        $editable = $this->isEditable();
        $me = auth()->user();
    @endphp

    <!-- Header -->
    <div class="mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-sm text-slate-400 dark:text-slate-500">{{ $meeting->number }}</span>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                    {{ $meeting->isPublished()
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                        : ($meeting->isCancelled()
                            ? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300') }}">
                    {{ $meeting->getStatusLabel() }}
                </span>
                @if($meeting->revisions->isNotEmpty())
                    <span class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                        {{ trans_choice('corrected once|corrected :count times', $meeting->revisions->count(), ['count' => $meeting->revisions->count()]) }}
                    </span>
                @endif
                @if($revising)
                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300">
                        {{ __('correcting the record') }}
                    </span>
                @endif
            </div>

            <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $meeting->title }}</h1>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ $meeting->meeting_date->format($dateFormat) }}
                @if($meeting->started_at) · {{ substr($meeting->started_at, 0, 5) }}@if($meeting->ended_at)–{{ substr($meeting->ended_at, 0, 5) }}@endif @endif
                @if($meeting->location) · {{ $meeting->location }} @endif
                @if($meeting->series) · {{ $meeting->series->name }} @endif
            </p>

            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                {{ __('Chair: :chair', ['chair' => $meeting->chair?->name ?? '—']) }}
                @if($meeting->secretary) · {{ __('Secretary: :name', ['name' => $meeting->secretary->name]) }} @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($meeting->isDraft() && $meeting->canEdit($me))
                <x-ui.button variant="secondary" size="sm" href="{{ route('meetings.agenda', $meeting) }}">{{ __('Agenda') }}</x-ui.button>
                <x-ui.button variant="secondary" size="sm" icon="edit" href="{{ route('meetings.edit', $meeting) }}">{{ __('Details') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm" x-on:click="$dispatch('open-modal', 'publish-modal')">{{ __('Publish Minute') }}</x-ui.button>
            @endif

            @if($meeting->isPublished() || $meeting->isDraft())
                <x-ui.button variant="secondary" size="sm" icon="eye"
                             href="{{ route('meetings.minute.pdf.view', $meeting) }}" target="_blank">
                    {{ __('PDF') }}
                </x-ui.button>
            @endif

            @if($meeting->isPublished() && ($meeting->chair_id === $me?->id || $me?->can('meetings.freeze')))
                <x-ui.button variant="secondary" size="sm" wire:click="resendMinute"
                             wire:confirm="{{ trans_choice(
                                'Send the minute to :count attendee again?|Send the minute to :count attendees again?',
                                $this->minuteRecipients->count(), ['count' => $this->minuteRecipients->count()]) }}">
                    {{ __('Send Again') }}
                </x-ui.button>
            @endif

            @if($meeting->isPublished() && $meeting->canRevise($me) && ! $revising)
                <x-ui.button variant="warning" size="sm" x-on:click="$dispatch('open-modal', 'revision-modal')">{{ __('Correct the Record') }}</x-ui.button>
            @endif

            @if($revising)
                <x-ui.button variant="secondary" size="sm" wire:click="cancelRevision">{{ __('Discard Correction') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm" wire:click="saveRevision">{{ __('Save Correction') }}</x-ui.button>
            @endif

            @if($meeting->isPublished() && $meeting->next_meeting_id === null && $me?->can('meetings.create'))
                <x-ui.button variant="secondary" size="sm" wire:click="createFollowUp">{{ __('Create the Next Meeting') }}</x-ui.button>
            @endif

            @if($meeting->canCancel($me))
                <x-ui.button variant="ghost" size="sm" x-on:click="$dispatch('open-modal', 'cancel-meeting-modal')" class="!text-red-600 dark:!text-red-400">
                    {{ __('Cancel Meeting') }}
                </x-ui.button>
            @endif

            <x-ui.button variant="secondary" size="sm" icon="arrow-left" href="{{ route('meetings.index') }}">{{ __('Back') }}</x-ui.button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    @if($meeting->isCancelled())
        <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-700/40">
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ __('Cancelled by :name on :date', ['name' => $meeting->cancelledBy?->name, 'date' => $meeting->cancelled_at?->format($stampFormat)]) }}
            </p>
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $meeting->cancel_reason }}</p>
        </div>
    @endif

    @if($meeting->isPublished())
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 dark:border-green-800 dark:bg-green-900/20">
            <p class="text-sm text-green-800 dark:text-green-300">
                {{ __('Published by :name on :date. This is the record; corrections are logged.', [
                    'name' => $meeting->publishedBy?->name,
                    'date' => $meeting->published_at?->format($stampFormat),
                ]) }}
            </p>
            <p class="mt-1 text-xs text-green-700 dark:text-green-400">
                @if($meeting->document)
                    {{ __('Filed in the project documents as ":name".', ['name' => $meeting->document->name]) }}
                @endif
                @php $notified = $meeting->attendees->whereNotNull('notified_at'); @endphp
                @if($notified->isNotEmpty())
                    {{ trans_choice('Sent to :count attendee.|Sent to :count attendees.', $notified->count(), ['count' => $notified->count()]) }}
                    {{ __('Last sent :date', ['date' => $notified->max('notified_at')?->format($stampFormat)]) }}
                @endif
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- The minute -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Opening notes -->
            <div class="{{ $card }} p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Notes') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Anything that belongs to the meeting as a whole rather than to one item.') }}
                </p>

                @if($editable)
                    <div class="mt-3" wire:ignore>
                        <x-ui.tinymce-editor wireModel="summary" id="meeting-summary-{{ $meeting->id }}" :height="240" />
                    </div>
                    <div class="mt-2 flex justify-end">
                        <x-ui.button variant="secondary" size="sm" wire:click="saveSummary">{{ __('Save Notes') }}</x-ui.button>
                    </div>
                @elseif($meeting->summary)
                    {{-- Sanitised again on the way out: strip_tags() alone keeps the
                         attributes of the tags it allows, so an onclick would survive
                         it. RichText drops every attribute it has not vouched for. --}}
                    <div class="rich-text mt-3 text-slate-700 dark:text-slate-200">
                        {!! App\Support\RichText::sanitize($meeting->summary) !!}
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">{{ __('None.') }}</p>
                @endif
            </div>

            <!-- The agenda, as it is being taken -->
            <div class="{{ $card }}"
                 x-data="{
                     open: @js($meeting->isPublished() ? $this->items->pluck('id')->all() : []),
                     toggle(id) {
                         this.open = this.isOpen(id) ? this.open.filter(i => i !== id) : [...this.open, id];
                     },
                     isOpen(id) { return this.open.includes(id) },
                     expandAll() { this.open = @js($this->items->pluck('id')->all()) },
                     collapseAll() { this.open = [] },
                 }">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('The Agenda') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $meeting->isDraft()
                                ? __('Open an item to take it. What is written here is what the minute will say.')
                                : __('What was discussed and what was decided.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if($this->items->isNotEmpty())
                            <button type="button" x-on:click="expandAll()" class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                {{ __('Expand all') }}
                            </button>
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <button type="button" x-on:click="collapseAll()" class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                {{ __('Collapse all') }}
                            </button>
                        @endif

                        @if($meeting->isDraft() && $meeting->canEdit($me))
                            <x-ui.button variant="primary" size="sm" icon="plus" wire:click="openItemForm">{{ __('Raise an Item') }}</x-ui.button>
                            <x-ui.button variant="secondary" size="sm" href="{{ route('meetings.agenda', $meeting) }}">{{ __('Carry Items Forward') }}</x-ui.button>
                        @endif
                    </div>
                </div>

                @if($showItemForm && ! $item_parent_id && ! $editingItemId)
                    @include('livewire.meeting.partials.item-form')
                @endif

                @if($this->items->isEmpty())
                    <div class="px-6 py-12 text-center">
                        <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Nothing on the agenda') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('A minute with no items cannot be published. Build the agenda first.') }}
                        </p>
                        @if($meeting->isDraft() && $meeting->canEdit($me))
                            <div class="mt-4">
                                <x-ui.button variant="primary" size="sm" href="{{ route('meetings.agenda', $meeting) }}">{{ __('Build the Agenda') }}</x-ui.button>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($this->items as $item)
                            @include('livewire.meeting.partials.minute-item', ['item' => $item, 'editable' => $editable])
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Corrections -->
            @if($meeting->revisions->isNotEmpty())
                <div class="{{ $card }}">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Corrections') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Everything changed after this minute was published.') }}
                        </p>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($meeting->revisions as $revision)
                            <div class="px-6 py-4" wire:key="revision-{{ $revision->id }}">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ __('Revision :number', ['number' => $revision->revision_number]) }}
                                    <span class="font-normal text-slate-500 dark:text-slate-400">
                                        — {{ $revision->revisedBy?->name }}, {{ $revision->created_at?->format($stampFormat) }}
                                    </span>
                                </p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $revision->reason }}</p>

                                @foreach($revision->changes ?? [] as $key => $change)
                                    <div class="mt-2 rounded-lg bg-slate-50 dark:bg-slate-700/40 px-3 py-2 text-xs">
                                        <p class="font-medium text-slate-600 dark:text-slate-300">
                                            {{ isset($change['item']) ? __('Item :number', ['number' => $change['item']]) : __('Notes') }}
                                        </p>
                                        <p class="mt-1 text-red-600 dark:text-red-400 line-through">{{ \Illuminate\Support\Str::limit($change['from'] ?: '—', 160) }}</p>
                                        <p class="text-green-700 dark:text-green-400">{{ \Illuminate\Support\Str::limit($change['to'] ?: '—', 160) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- The room, and where the meeting stands -->
        <div class="space-y-6">
            <div class="{{ $card }} p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Where It Stands') }}</h2>

                <div class="mt-3 grid grid-cols-2 gap-3">
                    @foreach([
                        ['label' => __('Items'), 'value' => $counters['discussed'].'/'.$counters['items'], 'hint' => __('discussed')],
                        ['label' => __('Decisions'), 'value' => $counters['decisions'], 'hint' => __('recorded')],
                        ['label' => __('Raised Here'), 'value' => $counters['raised_here'], 'hint' => __('new tasks')],
                        ['label' => __('Closed Here'), 'value' => $counters['closed_here'], 'hint' => __('confirmed')],
                    ] as $counter)
                        <div class="rounded-lg bg-slate-50 dark:bg-slate-700/40 px-3 py-2">
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $counter['label'] }}</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $counter['value'] }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ $counter['hint'] }}</p>
                        </div>
                    @endforeach
                </div>

                @if($counters['overdue'] > 0 || $counters['awaiting'] > 0)
                    <div class="mt-3 space-y-1 text-sm">
                        @if($counters['awaiting'] > 0)
                            <p class="text-amber-600 dark:text-amber-400">
                                {{ trans_choice(':count item is waiting for your confirmation|:count items are waiting for your confirmation', $counters['awaiting'], ['count' => $counters['awaiting']]) }}
                            </p>
                        @endif
                        @if($counters['overdue'] > 0)
                            <p class="text-red-600 dark:text-red-400">
                                {{ trans_choice(':count item on this agenda is overdue|:count items on this agenda are overdue', $counters['overdue'], ['count' => $counters['overdue']]) }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Attendance -->
            <div class="{{ $card }}">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Attendance') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __(':present of :invited present', ['present' => $counters['present'], 'invited' => $counters['invited']]) }}
                        @if($counters['unmarked'] > 0)
                            · <span class="text-amber-600 dark:text-amber-400">{{ trans_choice(':count not marked|:count not marked', $counters['unmarked'], ['count' => $counters['unmarked']]) }}</span>
                        @endif
                    </p>
                </div>

                @if($meeting->attendees->isEmpty())
                    <p class="px-6 py-6 text-sm text-slate-400 dark:text-slate-500">{{ __('Nobody on the register.') }}</p>
                @else
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($meeting->attendees as $attendee)
                            <div class="px-6 py-3" wire:key="attendee-{{ $attendee->id }}">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $attendee->displayName() }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ $attendee->getRoleLabel() }}
                                            @if($attendee->company) · {{ $attendee->company }} @endif
                                            @if($attendee->isExternal()) · {{ __('external') }} @endif
                                        </p>
                                    </div>

                                    @if($editable)
                                        {{-- Nothing is selected until somebody says; pressing the
                                             selected one again clears it. --}}
                                        <div class="inline-flex shrink-0 rounded-lg border {{ $attendee->isUnmarked() ? 'border-amber-300 dark:border-amber-700' : 'border-slate-300 dark:border-slate-600' }} overflow-hidden">
                                            @foreach(['present' => __('P'), 'absent' => __('A'), 'excused' => __('E')] as $value => $letter)
                                                <button type="button"
                                                        wire:click="setAttendance({{ $attendee->id }}, '{{ $attendee->attendance === $value ? '' : $value }}')"
                                                        title="{{ ['present' => __('Present'), 'absent' => __('Absent'), 'excused' => __('Excused')][$value] }}"
                                                        class="px-2 py-1 text-xs font-medium transition-colors
                                                            {{ $attendee->attendance === $value
                                                                ? 'bg-[#3F5189] text-white'
                                                                : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-700 dark:text-slate-300' }}">
                                                    {{ $letter }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="shrink-0 text-xs font-medium
                                            {{ $attendee->attendance === 'present' ? 'text-green-600 dark:text-green-400'
                                               : ($attendee->attendance === 'absent' ? 'text-red-600 dark:text-red-400'
                                               : ($attendee->isUnmarked() ? 'text-slate-400 dark:text-slate-500' : 'text-amber-600 dark:text-amber-400')) }}">
                                            {{ $attendee->getAttendanceLabel() }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- The next one -->
            <div class="{{ $card }} p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('The Next Meeting') }}</h2>

                @if($meeting->nextMeeting)
                    <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">
                        <a href="{{ route('meetings.show', $meeting->nextMeeting) }}" class="font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                            {{ $meeting->nextMeeting->number }}
                        </a>
                        — {{ $meeting->nextMeeting->meeting_date->format($dateFormat) }}
                    </p>
                @elseif($editable)
                    <input type="date" wire:model="nextMeetingDate" class="{{ $field }} mt-3">
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Agreed before everyone leaves. Nothing is scheduled automatically.') }}
                    </p>
                @elseif($meeting->next_meeting_date)
                    <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">{{ $meeting->next_meeting_date->format($dateFormat) }}</p>
                @else
                    <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">{{ __('Not agreed.') }}</p>
                @endif

                @if($meeting->previousMeeting)
                    <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
                        {{ __('Follows') }}
                        <a href="{{ route('meetings.show', $meeting->previousMeeting) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                            {{ $meeting->previousMeeting->number }}
                        </a>
                    </p>
                @endif
            </div>
        </div>
    </div>

    @include('livewire.meeting.partials.publish-modal')
    @include('livewire.meeting.partials.revision-modal')
    @include('livewire.meeting.partials.cancel-meeting-modal')
    @include('livewire.task.partials.detail-modal')
    @include('livewire.task.partials.form-modal')
    @include('livewire.task.partials.reason-modal')
</div>
