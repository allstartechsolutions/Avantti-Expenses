{{-- Meeting series form — full page: it carries the attendance list and the locations. --}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700';
@endphp

<x-ui.modal name="series-form-modal" maxWidth="full" layer="top">
    <form wire:submit="save" class="flex min-h-screen flex-col">
        <!-- Header -->
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        {{ $editingId ? __('Edit Series') : __('New Series') }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Every meeting created in this series starts from what you set here.') }}
                    </p>
                </div>
                <button type="button" wire:click="closeForm"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" title="{{ __('Close') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6 space-y-6">
            <!-- What it is -->
            <div class="{{ $card }} p-5 space-y-4">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('The Series') }}</h3>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">{{ __('Name') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" class="{{ $field }}" placeholder="{{ __('e.g. Weekly Site Meeting') }}">
                        @error('name') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('Code') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="code" class="{{ $field }} uppercase" placeholder="{{ __('e.g. SITE') }}" maxlength="20">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Used in every minute number: :example', ['example' => strtoupper($code ?: 'SITE').'-'.now()->year.'-014']) }}
                        </p>
                        @error('code') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('Cadence') }} <span class="text-red-500">*</span></label>
                        <select wire:model="cadence" class="{{ $field }}">
                            <option value="weekly">{{ __('Weekly') }}</option>
                            <option value="biweekly">{{ __('Every two weeks') }}</option>
                            <option value="monthly">{{ __('Monthly') }}</option>
                            <option value="quarterly">{{ __('Quarterly') }}</option>
                            <option value="ad_hoc">{{ __('As needed') }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Only suggests the next date. Nothing is scheduled automatically.') }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Agenda Order') }}</label>
                    <select wire:model="agenda_order" class="{{ $field }}">
                        <option value="last_meeting">{{ App\Models\MeetingSeries::agendaOrderLabel('last_meeting') }}</option>
                        <option value="overdue_first">{{ App\Models\MeetingSeries::agendaOrderLabel('overdue_first') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Every agenda groups its items by project and job site. This sets what happens inside each group when items are carried forward.') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ __('Usual Location') }}</label>
                        <input type="text" wire:model="default_location" class="{{ $field }}" placeholder="{{ __('e.g. Site office') }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Description') }}</label>
                        <input type="text" wire:model="description" class="{{ $field }}" placeholder="{{ __('What this meeting is for') }}">
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                    {{ __('Active — offered when creating a meeting') }}
                </label>
            </div>

            <!-- Who normally attends -->
            <div class="{{ $card }}">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Usual Attendees') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Copied onto every new meeting, where the register is then corrected on the day.') }}
                        </p>
                    </div>
                    <x-ui.button type="button" variant="secondary" size="sm" icon="plus" wire:click="addMember">{{ __('Add Attendee') }}</x-ui.button>
                </div>

                @if(empty($members))
                    <p class="px-5 py-6 text-sm text-slate-400 dark:text-slate-500">
                        {{ __('Nobody yet. Meetings in this series will start with an empty register.') }}
                    </p>
                @else
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($members as $index => $member)
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 px-5 py-3 items-start" wire:key="member-{{ $index }}">
                                <div class="md:col-span-4">
                                    <label class="{{ $label }} md:sr-only">{{ __('Person') }}</label>
                                    <select wire:model.live="members.{{ $index }}.user_id" class="{{ $field }}">
                                        <option value="">{{ __('Someone outside the company') }}</option>
                                        @foreach($this->users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @if(empty($member['user_id']))
                                    <div class="md:col-span-3">
                                        <input type="text" wire:model="members.{{ $index }}.name" class="{{ $field }}" placeholder="{{ __('Name') }}">
                                        @error("members.{$index}.name") <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <input type="text" wire:model="members.{{ $index }}.company" class="{{ $field }}" placeholder="{{ __('Company') }}">
                                    </div>
                                    <div class="md:col-span-2">
                                        <input type="email" wire:model="members.{{ $index }}.email" class="{{ $field }}" placeholder="{{ __('Email for the minutes') }}">
                                        @error("members.{$index}.email") <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                    </div>
                                @else
                                    <div class="md:col-span-7 flex items-center text-sm text-slate-500 dark:text-slate-400 md:pt-2">
                                        {{ __('A system user — the minutes go to their account email.') }}
                                    </div>
                                @endif

                                <div class="md:col-span-1 flex items-center gap-2">
                                    <select wire:model="members.{{ $index }}.role" class="{{ $field }} !px-2">
                                        <option value="chair">{{ __('Chair') }}</option>
                                        <option value="secretary">{{ __('Secretary') }}</option>
                                        <option value="participant">{{ __('Participant') }}</option>
                                    </select>
                                    <button type="button" wire:click="removeMember({{ $index }})"
                                            class="shrink-0 text-slate-400 hover:text-red-600" title="{{ __('Remove') }}">
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

            <!-- What it covers -->
            <div class="{{ $card }}">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Projects It Covers') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('A new meeting starts with these on the agenda, so their open items appear without anybody asking.') }}
                        </p>
                    </div>
                    <x-ui.button type="button" variant="secondary" size="sm" icon="plus" wire:click="addScope">{{ __('Add Location') }}</x-ui.button>
                </div>

                @if(empty($scopes))
                    <p class="px-5 py-6 text-sm text-slate-400 dark:text-slate-500">
                        {{ __('Nothing yet. You can still add projects to each meeting by hand.') }}
                    </p>
                @else
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($scopes as $index => $scope)
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 px-5 py-3 items-start" wire:key="scope-{{ $index }}">
                                <div class="md:col-span-5">
                                    <select wire:model.live="scopes.{{ $index }}.project_id" class="{{ $field }}">
                                        <option value="">{{ __('Choose a project') }}</option>
                                        @foreach($this->projects as $project)
                                            <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                                        @endforeach
                                    </select>
                                    @error("scopes.{$index}.project_id") <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-6">
                                    <select wire:model="scopes.{{ $index }}.job_site_id" class="{{ $field }}" @disabled(empty($scope['project_id']))>
                                        <option value="">{{ __('The whole project, every job site') }}</option>
                                        @foreach($this->jobSitesByProject[$scope['project_id']] ?? [] as $site)
                                            <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="md:col-span-1 flex md:justify-end">
                                    <button type="button" wire:click="removeScope({{ $index }})"
                                            class="text-slate-400 hover:text-red-600 md:pt-2" title="{{ __('Remove') }}">
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

        <!-- Footer -->
        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-end gap-3">
                <x-ui.button type="button" variant="secondary" wire:click="closeForm">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="save">
                    {{ $editingId ? __('Save Changes') : __('Create Series') }}
                </x-ui.button>
            </div>
        </div>
    </form>
</x-ui.modal>
