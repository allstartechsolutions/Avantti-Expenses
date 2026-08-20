{{--
    What this person can actually reach: their scope, the projects and job
    sites they have been added to, and what has been done to their access.

    The Users screen could say somebody was confined but never what they were
    confined *to* — which is the question anybody asks next.
--}}
@php
    $effective = $user->effectiveAccessScope();
    $followsRole = $user->followsRoleScope();
@endphp

<div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Access') }}</h3>
        <div class="flex items-center gap-2">
            @if($user->is_guest)
                <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Guest') }}</span>
            @endif
            <span class="px-2 py-0.5 text-xs rounded-full {{ $effective === \App\Enums\AccessScope::ASSIGNED ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                {{ __($effective->label()) }}
            </span>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <div>
            <p class="text-sm text-slate-700 dark:text-slate-300">{{ __($effective->description()) }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                @if($user->is_guest)
                    {{ __('A guest is always confined to what they were added to.') }}
                @elseif($followsRole)
                    {{ __('This follows the :role role. Change it for everybody on the Roles & Access screen, or for this one person by editing them.', ['role' => __($user->role?->name ?? 'none')]) }}
                @else
                    {{ __('Set on this person specifically, overriding the :role role.', ['role' => __($user->role?->name ?? 'none')]) }}
                @endif
            </p>

            @unless(\App\Services\AbilityCatalog::isSwept('project'))
                <p class="text-sm text-amber-700 dark:text-amber-400 mt-2">
                    {{ __('Recorded but not enforced yet: the project screens have not been converted, so every project is still listed to everybody.') }}
                </p>
            @endunless
        </div>

        <div>
            <h4 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
                {{ __('Projects and job sites') }}
            </h4>

            @forelse($this->memberships as $membership)
                @php $scope = $membership->scopeable; @endphp
                <div class="py-3 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if($scope)
                                <a href="{{ $scope instanceof \App\Models\JobSite ? route('jobsites.team', $scope) : route('projects.team', $scope) }}"
                                   class="text-sm font-medium text-[#3F5189] dark:text-[#8fa0d8] hover:underline">
                                    {{ $scope->scopeLabel() }}
                                </a>
                                <span class="px-2 py-0.5 text-xs rounded bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    {{ $scope instanceof \App\Models\JobSite ? __('Job site') : __('Project') }}
                                </span>
                            @else
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('(deleted)') }}</span>
                            @endif

                            <span class="px-2 py-0.5 text-xs rounded-full
                                {{ $membership->status === \App\Enums\MembershipStatus::ACTIVE ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                                {{ __($membership->status->label()) }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            {{ __($membership->accessLabel()) }} ·
                            {{ trans_choice('{0} No abilities|{1} :count ability|[2,*] :count abilities', count($membership->abilities()), ['count' => count($membership->abilities())]) }}
                            @unless($membership->can_see_money) · {{ __('No monetary figures') }} @endunless
                            @if($membership->title) · {{ $membership->title }} @endif
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @if($effective === \App\Enums\AccessScope::ASSIGNED)
                        {{ __('Not added to anything yet — so once the project screens are converted, this person will see nothing.') }}
                    @else
                        {{ __('Not added to any project. They reach every project through their role instead.') }}
                    @endif
                </p>
            @endforelse
        </div>

        @if($this->accessHistory->count())
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
                    {{ __('Access history') }}
                </h4>
                @foreach($this->accessHistory as $entry)
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0 flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $entry->summary }}</span>
                        <span class="text-xs text-slate-400 dark:text-slate-500">
                            {{ $entry->actor?->name ?? __('System') }} · {{ $entry->created_at?->diffForHumans() }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
