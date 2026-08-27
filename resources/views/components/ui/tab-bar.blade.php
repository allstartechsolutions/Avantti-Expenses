@props([
    'entries' => [],
    'scope',
])

{{--
    The project and job-site tab bar.

    `entries` comes from App\Services\Navigation::projectTabBar() /
    jobSiteTabBar(): a flat, ordered list of items and dropdown groups, already
    filtered to what this person may open. There is no tab list in this file.

    `scope` is the Project or JobSite the routes are bound to.

    The bar wraps rather than scrolling sideways: a dropdown panel inside an
    overflow-x-auto strip is clipped by it, and a phone showing two short rows
    reads better than one row that has to be dragged.
--}}

@php
    $activeClasses = 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]';
    $idleClasses = 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300 dark:hover:border-slate-600';
@endphp

@if(count($entries))
    <div class="mb-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="-mb-px flex flex-wrap items-center gap-x-6 sm:gap-x-8" aria-label="{{ __('navigation.sections') }}">
                @foreach($entries as $entry)
                    @if($entry['type'] === 'group')
                        @php
                            $activeChild = collect($entry['items'])->firstWhere('active', true);
                        @endphp
                        <div
                            class="relative"
                            x-data="{ open: false }"
                            @keydown.escape.stop="open = false"
                        >
                            <button
                                type="button"
                                @click="open = ! open"
                                @click.outside="open = false"
                                aria-haspopup="true"
                                :aria-expanded="open ? 'true' : 'false'"
                                aria-label="{{ __('navigation.open_section', ['section' => $entry['name']]) }}"
                                class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3F5189] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 rounded-sm {{ $entry['active'] ? $activeClasses : $idleClasses }}"
                            >
                                @if($entry['icon'])
                                    <svg class="mr-2 -ml-1 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $entry['icon'] }}"></path>
                                    </svg>
                                @endif
                                {{ $entry['name'] }}
                                @if($activeChild)
                                    <span class="hidden md:inline ml-1.5 text-xs font-normal opacity-70">/ {{ $activeChild['name'] }}</span>
                                @endif
                                <svg class="ml-1.5 h-4 w-4 shrink-0 transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute left-0 z-50 mt-1 w-64 max-w-[calc(100vw-2rem)] origin-top-left rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800"
                            >
                                @foreach($entry['items'] as $item)
                                    <a
                                        href="{{ route($item['route'], $scope) }}"
                                        @class([
                                            'flex items-center px-4 py-2.5 text-sm transition-colors',
                                            'bg-slate-100 font-medium text-[#3F5189] dark:bg-slate-700/60 dark:text-[#8FA0DC]' => $item['active'],
                                            'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/40' => ! $item['active'],
                                        ])
                                    >
                                        @if($item['icon'])
                                            <svg class="mr-3 h-5 w-5 shrink-0 {{ $item['active'] ? '' : 'text-slate-400 dark:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                                            </svg>
                                        @endif
                                        {{ $item['name'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a
                            href="{{ route($entry['route'], $scope) }}"
                            class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap {{ $entry['active'] ? $activeClasses : $idleClasses }}"
                        >
                            @if($entry['icon'])
                                <svg class="mr-2 -ml-1 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $entry['icon'] }}"></path>
                                </svg>
                            @endif
                            {{ $entry['name'] }}
                        </a>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>
@endif
