{{--
    The top bar an outside guest sees: the company, where they are, and their
    own account. No global search, no company-wide anything — a guest can only
    reach what they were given.
--}}
@php
    $memberships = auth()->user()?->activeMemberships()->with('scopeable')->get() ?? collect();
@endphp

<header class="bg-white dark:bg-slate-800 shadow-sm border-b border-slate-200 dark:border-slate-700 px-4 sm:px-6 py-3">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 shrink-0 bg-[#3F5189] rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-sm leading-none">
                    {{ mb_substr(\App\Models\Company::first()?->name ?? config('app.name'), 0, 1) }}
                </span>
            </div>
            <span class="text-base font-semibold text-slate-900 dark:text-white truncate">
                {{ \App\Models\Company::first()?->name ?? config('app.name') }}
            </span>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
            @if($memberships->count() > 1)
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button @click="open = !open"
                            class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="hidden sm:inline">{{ __('Switch') }}</span>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 z-50 py-1">
                        @foreach($memberships as $membership)
                            @php $scope = $membership->scopeable; @endphp
                            @if($scope)
                                <a href="{{ $scope instanceof \App\Models\JobSite ? route('jobsites.overview', $scope) : route('projects.overview', $scope) }}"
                                   class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                                    {{ $scope instanceof \App\Models\JobSite ? $scope->job_site_name : $scope->project_name }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button @click="open = !open" class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                    <div class="w-8 h-8 bg-slate-200 dark:bg-slate-700 rounded-full flex items-center justify-center">
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ auth()->user()->initials() }}</span>
                    </div>
                    <span class="hidden sm:block text-sm text-slate-700 dark:text-slate-300">{{ auth()->user()->name }}</span>
                </button>
                <div x-show="open" x-cloak
                     class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 z-50">
                    <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                        <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
                    </div>
                    <div class="py-1">
                        <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">{{ __('Profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">{{ __('Logout') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
