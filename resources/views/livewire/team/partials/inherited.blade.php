{{--
    Job-site level only: the people who reach this site through the project.
    The cascade made visible — without this the list would look wrong to
    anybody who knows the project has a team.
--}}
@php
    $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
    $canInvite = auth()->user()?->can('team.invite', $jobSite);
@endphp

@if($this->inheritedMembers->count())
    <div class="{{ $card }} overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('From the project') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('These people reach this job site through :project. Give somebody their own access here to replace what the project gives them on this site.', ['project' => $jobSite->project?->project_name]) }}
            </p>
        </div>

        @foreach($this->inheritedMembers as $membership)
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 shrink-0 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center">
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ $membership->user?->initials() }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ $membership->user?->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __($membership->accessLabel()) }} ·
                            {{ trans_choice('{0} No abilities|{1} :count ability|[2,*] :count abilities', count($membership->abilities()), ['count' => count($membership->abilities())]) }}
                            @unless($membership->can_see_money) · {{ __('No monetary figures') }} @endunless
                        </p>
                    </div>
                </div>

                @if($canInvite)
                    <x-ui.button variant="ghost" size="sm" wire:click="overrideInherited({{ $membership->id }})">
                        {{ __('Give this site its own') }}
                    </x-ui.button>
                @endif
            </div>
        @endforeach
    </div>
@endif
