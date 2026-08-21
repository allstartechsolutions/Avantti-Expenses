@php
    /*
     |--------------------------------------------------------------------------
     | The effective-access inspector (F1)
     |--------------------------------------------------------------------------
     |
     | "Why can't Maria see the budget?" has four possible answers — her role,
     | her own exceptions, her project memberships, or the module being switched
     | off — and until this screen existed, finding out which meant opening four
     | others. Every line here is the answer PermissionResolver itself gives,
     | with the reason next to it.
     */

    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';

    $sections = $this->effective;
    $scopes = $this->scopes;
    $currency = config('app.currency');
    $locale = config('app.locale');

    $limit = $user->effectiveApprovalLimit();
@endphp

<div class="space-y-6">
    {{-- The shape of their access in four facts --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="{{ $card }} p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Role') }}</p>
            <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                {{ $user->role?->name ? ucfirst($user->role->name) : __('No role') }}
            </p>
        </div>

        <div class="{{ $card }} p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Projects they can see') }}</p>
            <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                {{ $user->isConfined() ? __('Only the ones they are added to') : __('Every one') }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $user->followsRoleScope() ? __('From their role') : __('Set on this person') }}
            </p>
        </div>

        <div class="{{ $card }} p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Approval limit') }}</p>
            <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                {{ $limit === null ? __('No limit') : Number::currency($limit / 100, $currency, $locale) }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $user->followsRoleApprovalLimit() ? __('From their role') : __('Set on this person') }}
            </p>
        </div>

        <div class="{{ $card }} p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Sees monetary figures') }}</p>
            <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                {{ app(\App\Services\PermissionResolver::class)->canSeeMoney($user) ? __('Yes') : __('No') }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('On the company-wide screens') }}</p>
        </div>
    </div>

    {{-- The projects they are on --}}
    <div class="{{ $card }} overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Projects and job sites they are on') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('A membership replaces the role on the project it covers, and a project membership carries down to every job site under it.') }}
            </p>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse ($scopes as $scope)
                <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                            {{ $scope['label'] }}
                            <span class="text-xs font-normal text-slate-500 dark:text-slate-400">
                                {{ $scope['level'] === 'job_site' ? __('job site') : __('project') }}
                            </span>
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $scope['title'] ?: __('No title') }} ·
                            {{ trans_choice('{0} nothing granted|{1} :count ability|[2,*] :count abilities', $scope['count'], ['count' => $scope['count']]) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($scope['limit'] !== null)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                {{ __('Up to :amount', ['amount' => Number::currency($scope['limit'] / 100, $currency, $locale)]) }}
                            </span>
                        @endif
                        @unless ($scope['money'])
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ __('Totals hidden') }}
                            </span>
                        @endunless
                        <span class="text-xs px-2 py-0.5 rounded-full
                            {{ $scope['status']->value === 'active'
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' }}">
                            {{ __(ucfirst($scope['status']->value)) }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    @if ($user->isConfined())
                        {{ __('They are on nothing, so they see no project at all. Add them to a project\'s team to give them work.') }}
                    @else
                        {{ __('They are on no project team, and do not need to be: they can reach every project.') }}
                    @endif
                </div>
            @endforelse
        </div>
    </div>

    {{-- Every ability, with the reason --}}
    <div>
        <input type="text" wire:model.live.debounce.200ms="matrixSearch"
               class="{{ $field }} md:max-w-sm mb-4" placeholder="{{ __('Filter areas…') }}">

        <div class="space-y-4">
            @forelse ($sections as $section)
                <div class="{{ $card }} overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $section['name'] }}</h3>
                        @if ($section['scoped'])
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                  title="{{ __('On a project, this person\'s membership there answers instead.') }}">{{ __('Per project') }}</span>
                        @endif
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach ($section['rows'] as $row)
                            <div class="px-5 py-2.5 flex items-center justify-between gap-4">
                                <span class="text-sm text-slate-700 dark:text-slate-300 min-w-0 truncate">{{ $row['name'] }}</span>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $row['source'] }}</span>
                                    @if ($row['allowed'])
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 dark:text-green-400">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ __('Allowed') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-400 dark:text-slate-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            {{ __('No') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="{{ $card }} px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Nothing matches ":term".', ['term' => $matrixSearch]) }}
                </div>
            @endforelse
        </div>
    </div>

    @if ($user->isConfined())
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-3xl">
            {{ __('The answers above are for the company-wide screens. Anything belonging to a project is answered by this person\'s membership on that project, listed further up.') }}
        </p>
    @endif
</div>
