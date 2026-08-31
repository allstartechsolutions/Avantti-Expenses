<div>
    @php
        $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
        $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
        $editing = $meeting?->exists;
    @endphp

    <!-- Page header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="font-mono text-sm text-slate-400 dark:text-slate-500">{{ $this->numberPreview }}</span>
                @unless($editing)
                    <span class="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('number reserved on save') }}
                    </span>
                @endunless
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                {{ $editing ? __('Edit Meeting') : __('New Meeting') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('When it is, where it is, and who is in the room. The agenda is built on the meeting itself.') }}
            </p>
        </div>
        <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('meetings.index') }}">{{ __('Back') }}</x-ui.button>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- When and where -->
        <div class="lg:col-span-2 space-y-6">
            <div class="{{ $card }} p-6 space-y-4">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('The Meeting') }}</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ __('Series') }}</label>
                        <select wire:model.live="meeting_series_id" class="{{ $field }}">
                            <option value="">{{ __('One-off meeting, no series') }}</option>
                            @foreach($this->seriesOptions as $series)
                                <option value="{{ $series->id }}">{{ $series->name }} ({{ $series->code }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('A series brings its people, its projects and its open items with it.') }}
                        </p>
                        @error('meeting_series_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="title" class="{{ $field }}" placeholder="{{ __('e.g. Weekly Site Meeting') }}">
                        @error('title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ __('Date') }} <span class="text-red-500">*</span></label>
                        <x-ui.date-input wire:model.live="meeting_date" class="{{ $field }}" />
                        @error('meeting_date') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Starts') }}</label>
                        <input type="time" wire:model="started_at" class="{{ $field }}">
                        @error('started_at') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Ends') }}</label>
                        <input type="time" wire:model="ended_at" class="{{ $field }}">
                        @error('ended_at') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ __('Location') }}</label>
                        <input type="text" wire:model="location" class="{{ $field }}" placeholder="{{ __('e.g. Site office') }}">
                        @error('location') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Meeting Link') }}</label>
                        <input type="url" wire:model="meeting_url" class="{{ $field }}" placeholder="https://...">
                        @error('meeting_url') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ __('Chair') }} <span class="text-red-500">*</span></label>
                        <select wire:model="chair_id" class="{{ $field }}">
                            <option value="">{{ __('Choose a chair') }}</option>
                            @foreach($this->users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('The chair confirms the tasks their owners declared ready.') }}
                        </p>
                        @error('chair_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Secretary') }}</label>
                        <select wire:model="secretary_id" class="{{ $field }}">
                            <option value="">{{ __('Nobody in particular') }}</option>
                            @foreach($this->users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Who writes the minute.') }}</p>
                        @error('secretary_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- The register -->
            <div class="{{ $card }}">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Attendance') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Everyone invited. Mark who actually turned up — the minute records both.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($meeting_series_id)
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="loadSeriesAttendees">
                                {{ __('Reload from series') }}
                            </x-ui.button>
                        @endif
                        <x-ui.button type="button" variant="secondary" size="sm" icon="plus" wire:click="addAttendee">
                            {{ __('Add Attendee') }}
                        </x-ui.button>
                    </div>
                </div>

                @if(empty($attendees))
                    <div class="px-6 py-8 text-center">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ $meeting_series_id
                                ? __('Nobody on the register. Reload the series list, or add people one by one.')
                                : __('Nobody on the register yet. Choose a series to bring its usual attendees in, or add them one by one.') }}
                        </p>
                    </div>
                @else
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($attendees as $index => $attendee)
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 px-6 py-3 items-start" wire:key="attendee-{{ $index }}">
                                <div class="md:col-span-4">
                                    <select wire:model.live="attendees.{{ $index }}.user_id" class="{{ $field }}">
                                        <option value="">{{ __('Someone outside the company') }}</option>
                                        @foreach($this->users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @if(empty($attendee['user_id']))
                                    <div class="md:col-span-3">
                                        <input type="text" wire:model="attendees.{{ $index }}.name" class="{{ $field }}" placeholder="{{ __('Name') }}">
                                        @error("attendees.{$index}.name") <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <input type="text" wire:model="attendees.{{ $index }}.company" class="{{ $field }}" placeholder="{{ __('Company') }}">
                                    </div>
                                    <div class="md:col-span-3 flex gap-2">
                                        <input type="email" wire:model="attendees.{{ $index }}.email" class="{{ $field }}" placeholder="{{ __('Email for the minutes') }}">
                                    </div>
                                @else
                                    <div class="md:col-span-8 flex items-center text-sm text-slate-500 dark:text-slate-400 md:pt-2">
                                        {{ __('A system user — the minutes go to their account email.') }}
                                    </div>
                                @endif

                                <div class="md:col-span-12 flex flex-wrap items-center gap-2">
                                    <select wire:model="attendees.{{ $index }}.role" class="{{ $field }} !w-auto">
                                        <option value="chair">{{ __('Chair') }}</option>
                                        <option value="secretary">{{ __('Secretary') }}</option>
                                        <option value="participant">{{ __('Participant') }}</option>
                                    </select>

                                    <div class="inline-flex rounded-lg border border-slate-300 dark:border-slate-600 overflow-hidden">
                                        @foreach(['present' => __('Present'), 'absent' => __('Absent'), 'excused' => __('Excused')] as $value => $text)
                                            <button type="button"
                                                    wire:click="$set('attendees.{{ $index }}.attendance', '{{ ($attendee['attendance'] ?? '') === $value ? '' : $value }}')"
                                                    class="px-3 py-2 text-xs font-medium transition-colors
                                                        {{ ($attendee['attendance'] ?? '') === $value
                                                            ? 'bg-[#3F5189] text-white'
                                                            : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600' }}">
                                                {{ $text }}
                                            </button>
                                        @endforeach
                                    </div>

                                    <button type="button" wire:click="removeAttendee({{ $index }})"
                                            class="ml-auto text-slate-400 hover:text-red-600" title="{{ __('Remove') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- What this meeting will start from -->
        <div class="space-y-6">
            <div class="{{ $card }} p-6">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('What It Starts From') }}</h3>

                @if($this->previousMeeting)
                    <div class="mt-3 rounded-lg bg-slate-50 dark:bg-slate-700/40 p-4">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Previous meeting in this series') }}</p>
                        <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ $this->previousMeeting->number }}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            {{ $this->previousMeeting->meeting_date->appDate() }} — {{ $this->previousMeeting->title }}
                        </p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ trans_choice(':count open action item carries forward|:count open action items carry forward',
                                $this->previousMeeting->openActionCount(),
                                ['count' => $this->previousMeeting->openActionCount()]) }}
                        </p>
                    </div>
                @elseif($meeting_series_id)
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('This is the first meeting of the series, so nothing carries forward yet.') }}
                    </p>
                @else
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('A one-off meeting carries nothing forward. Choose a series if this is a meeting you hold regularly.') }}
                    </p>
                @endif

                @if($editing)
                    <x-ui.button variant="secondary" class="mt-4 w-full justify-center" href="{{ route('meetings.agenda', $meeting) }}">
                        {{ __('Build the Agenda') }}
                    </x-ui.button>
                @else
                    <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
                        {{ __('The agenda itself is built on the meeting once it exists.') }}
                    </p>
                @endif
            </div>

            <div class="{{ $card }} p-6 space-y-3">
                <x-ui.button type="submit" variant="primary" icon="save" class="w-full justify-center">
                    {{ $editing ? __('Save Changes') : __('Create Meeting') }}
                </x-ui.button>
                <x-ui.button type="button" variant="secondary" href="{{ route('meetings.index') }}" class="w-full justify-center">
                    {{ __('Cancel') }}
                </x-ui.button>
            </div>
        </div>
    </form>
</div>
