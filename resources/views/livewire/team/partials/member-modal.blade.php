{{--
    Add or edit one person's access on this project or job site. Full page:
    the matrix is a hundred-odd repeating rows with a running total.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700';
    $scope = $this->isJobSiteLevel() ? $jobSite : $project;
    $scopeName = $this->isJobSiteLevel() ? $jobSite->job_site_name : $project->project_name;
    $membership = $this->editingMembership;
    $readOnly = ! auth()->user()?->can($membership ? 'team.manage' : 'team.invite', $scope);
@endphp

<x-ui.modal name="member-modal" maxWidth="full">
    @if($showMemberModal)
    <form wire:submit="saveMember" class="flex min-h-screen flex-col">
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        @if($membership)
                            {{ __('Access for :name', ['name' => $membership->user?->name]) }}
                        @elseif($inviting)
                            {{ $inviteAsGuest ? __('Invite a client or other outsider') : __('Invite somebody by e-mail') }}
                        @else
                            {{ __('Add Member') }}
                        @endif
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                        {{ $scopeName }} · {{ $this->isJobSiteLevel() ? __('One job site') : __('A whole project') }}
                    </p>
                </div>
                <button type="button" wire:click="closeMemberModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" title="{{ __('Close') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6 space-y-6">
            <div class="{{ $card }} p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="{{ $label }}">{{ $inviting ? __('Who are you inviting?') : __('Person') }} <span class="text-red-500">*</span></label>
                    @if($inviting)
                        <input type="email" wire:model="inviteEmail" class="{{ $field }} mb-2"
                               placeholder="{{ __('their@email.com') }}" @disabled($readOnly)>
                        @error('inviteEmail') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror

                        <input type="text" wire:model="inviteName" class="{{ $field }}"
                               placeholder="{{ __('Their name (optional)') }}" @disabled($readOnly)>
                        @error('inviteName') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror

                        <label class="mt-3 flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="inviteAsGuest"
                                   class="mt-1 h-4 w-4 rounded border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700"
                                   @disabled($readOnly)>
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('This is an outsider') }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('A client, engineer or vendor. They get no sidebar, no search and nothing company-wide — only what you give them here, and never any monetary figures.') }}</span>
                            </span>
                        </label>

                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('They will get an e-mail with a link to choose a password. No account exists until they use it.') }}
                        </p>
                    @elseif($membership)
                        <div class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $membership->user?->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $membership->user?->email }}</p>
                        </div>
                    @else
                        <input type="text" wire:model.live.debounce.250ms="memberSearch" class="{{ $field }} mb-2"
                               placeholder="{{ __('Search by name or e-mail…') }}" @disabled($readOnly)>
                        <select wire:model="userId" class="{{ $field }}" size="6" @disabled($readOnly)>
                            @forelse($this->addableUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                            @empty
                                <option disabled>{{ __('Nobody left to add.') }}</option>
                            @endforelse
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Only people who already have a login. Inviting somebody new comes next.') }}
                        </p>
                    @endif
                    @error('userId') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div class="md:col-span-2 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">{{ __('Start from a template') }}</label>
                            <select wire:model.live="templateId" class="{{ $field }}" @disabled($readOnly)>
                                <option value="">{{ __('Nothing — start empty') }}</option>
                                @foreach($this->templates as $template)
                                    <option value="{{ $template->id }}">{{ __($template->name) }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Loads that template\'s abilities. Change anything you like afterwards — the person keeps what you save, not what the template says later.') }}
                            </p>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Title on this :level', ['level' => $this->isJobSiteLevel() ? __('job site') : __('project')]) }}</label>
                            <input type="text" wire:model="title" class="{{ $field }}" placeholder="{{ __('e.g. Engenheiro residente') }}" @disabled($readOnly)>
                            @error('title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">{{ __('Approval limit') }}</label>
                            <input type="number" step="0.01" min="0" wire:model="approvalLimit" class="{{ $field }}"
                                   placeholder="{{ __('No limit') }}" @disabled($readOnly)>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Caps what this person may approve, award or release here.') }}</p>
                            @error('approvalLimit') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <label class="flex items-start gap-3 cursor-pointer pt-6 {{ $inviting && $inviteAsGuest ? 'opacity-50' : '' }}">
                            <input type="checkbox" wire:model.live="canSeeMoney"
                                   class="mt-1 h-4 w-4 rounded border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700"
                                   @disabled($readOnly || ($inviting && $inviteAsGuest))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('See monetary figures') }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Off, and every total, budget figure and financial report is hidden from this person here.') }}</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- The sentence that says, in plain words, what is about to be
                 saved — and the rule that surprises people: on this project,
                 this list replaces their role rather than adding to it. --}}
            <div class="p-4 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300 text-sm">
                <p>{{ $this->accessSummary }}</p>
                <p class="mt-1 text-slate-500 dark:text-slate-400">
                    {{ $this->isJobSiteLevel()
                        ? __('On this job site this replaces what their role gives them — and what the project gives them. Everywhere else they are unchanged.')
                        : __('On this project this replaces what their role gives them. Everywhere else they are unchanged.') }}
                </p>
            </div>

            @include('livewire.access.partials.ability-matrix', [
                'sections' => $this->matrix,
                'readOnly' => $readOnly,
                'search' => $matrixSearch,
            ])
        </div>

        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __(':count abilities granted', ['count' => $this->grantedCount]) }}
                </p>
                <div class="flex items-center gap-3">
                    <x-ui.button variant="secondary" type="button" wire:click="closeMemberModal">{{ __('Cancel') }}</x-ui.button>
                    @unless($readOnly)
                        <x-ui.button variant="primary" type="submit" icon="save">{{ $membership ? __('Save Access') : ($inviting ? __('Send Invitation') : __('Add to Team')) }}</x-ui.button>
                    @endunless
                </div>
            </div>
        </div>
    </form>
    @endif
</x-ui.modal>
