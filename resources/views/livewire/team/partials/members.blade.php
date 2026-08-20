{{--
    The team list. Expects $scopeLabel (the project or job site name).
--}}
@php
    $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
    $scope = $this->isJobSiteLevel() ? $jobSite : $project;
    $canInvite = auth()->user()?->can('team.invite', $scope);
    $canManage = auth()->user()?->can('team.manage', $scope);
    $active = $this->members->where('status', \App\Enums\MembershipStatus::ACTIVE)->count();
@endphp

<div class="{{ $card }} overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Team') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                @if($this->isJobSiteLevel())
                    {{ __('People added to this job site specifically. What they are given here replaces what the project gives them.') }}
                @else
                    {{ __('People added to this project. What they are given here also covers every job site under it.') }}
                @endif
            </p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <span class="text-sm text-slate-500 dark:text-slate-400">
                {{ trans_choice('{0} Nobody yet|{1} :count member|[2,*] :count members', $this->members->count(), ['count' => $this->members->count()]) }}
            </span>
            @if($canInvite)
                <div class="flex items-center gap-2">
                    <x-ui.button variant="primary" size="sm" wire:click="addMember" icon="plus">{{ __('Add Member') }}</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" wire:click="inviteSomebody(false)" icon="plus">{{ __('Invite by e-mail') }}</x-ui.button>
                    <x-ui.button variant="ghost" size="sm" wire:click="inviteSomebody(true)" icon="plus">{{ __('Invite a client') }}</x-ui.button>
                </div>
            @endif
        </div>
    </div>

    @forelse($this->members as $membership)
        @php $chips = $this->areaChips($membership); @endphp
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1 flex items-start gap-3">
                    <div class="w-10 h-10 shrink-0 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $membership->user?->initials() }}</span>
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $membership->user?->name ?? __('Unknown') }}</h3>

                            @if($membership->user?->is_guest)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Guest') }}</span>
                            @endif

                            <span class="px-2 py-0.5 text-xs rounded-full
                                {{ $membership->status === \App\Enums\MembershipStatus::ACTIVE ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : '' }}
                                {{ $membership->status === \App\Enums\MembershipStatus::SUSPENDED ? 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300' : '' }}
                                {{ $membership->status === \App\Enums\MembershipStatus::INVITED ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : '' }}">
                                {{ __($membership->status->label()) }}
                            </span>

                            @unless($membership->can_see_money)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('No monetary figures') }}</span>
                            @endunless
                        </div>

                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $membership->user?->email }}
                            @if($membership->title) · {{ $membership->title }} @endif
                        </p>

                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-300">{{ __($membership->accessLabel()) }}</span>
                            <span class="text-slate-400 dark:text-slate-500" aria-hidden="true">·</span>
                            <span class="text-slate-500 dark:text-slate-400">{{ trans_choice('{0} No abilities|{1} :count ability|[2,*] :count abilities', count($membership->abilities()), ['count' => count($membership->abilities())]) }}</span>
                            @if($membership->approval_limit)
                                <span class="text-slate-400 dark:text-slate-500" aria-hidden="true">·</span>
                                <span class="text-slate-500 dark:text-slate-400">{{ __('Approves up to :amount', ['amount' => number_format($membership->approval_limit / 100, 2)]) }}</span>
                            @endif
                        </div>

                        @if(count($chips))
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach(array_slice($chips, 0, 8) as $chip)
                                    <span class="px-2 py-0.5 text-xs rounded bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $chip }}</span>
                                @endforeach
                                @if(count($chips) > 8)
                                    <span class="px-2 py-0.5 text-xs rounded bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">{{ __('+:count more', ['count' => count($chips) - 8]) }}</span>
                                @endif
                            </div>
                        @else
                            <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">{{ __('Added, but given nothing yet.') }}</p>
                        @endif

                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                            @if($membership->invitedBy)
                                {{ __('Added by :who :when', ['who' => $membership->invitedBy->name, 'when' => $membership->invited_at?->diffForHumans()]) }}
                            @else
                                {{ __('Added :when', ['when' => $membership->created_at?->diffForHumans()]) }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <x-ui.button variant="secondary" size="sm" wire:click="editMember({{ $membership->id }})" icon="edit">
                        {{ $canManage ? __('Edit access') : __('View access') }}
                    </x-ui.button>
                    @if($canManage)
                        <x-ui.button variant="ghost" size="sm" wire:click="suspendMember({{ $membership->id }})">
                            {{ $membership->status === \App\Enums\MembershipStatus::SUSPENDED ? __('Restore') : __('Suspend') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            size="sm"
                            icon="trash"
                            wire:click="removeMember({{ $membership->id }})"
                            wire:confirm="{{ __('Remove this person from the team? What they did stays on the record.') }}">
                            {{ __('Remove') }}
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="px-6 py-12 text-center">
            <p class="text-slate-900 dark:text-white font-medium">{{ __('Nobody has been added to :name yet.', ['name' => $scopeLabel]) }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-xl mx-auto">
                @if($this->isJobSiteLevel())
                    {{ __('Everybody who is on the project can already reach this site. Add somebody here only when this site should give them something different.') }}
                @else
                    {{ __('Add the people who work on this project and choose what each of them may do. Company-wide users can already see everything; a member list is what makes it possible to confine somebody to their own work.') }}
                @endif
            </p>
            @if($canInvite)
                <div class="mt-4 flex items-center justify-center gap-2 flex-wrap">
                    <x-ui.button variant="primary" size="sm" wire:click="addMember" icon="plus">{{ __('Add Member') }}</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" wire:click="inviteSomebody(false)" icon="plus">{{ __('Invite by e-mail') }}</x-ui.button>
                </div>
            @endif
        </div>
    @endforelse
</div>
