@php
    $groups = [
        [
            'key'   => 'projects',
            'label' => __('Projects'),
            'rows'  => $this->projects,
        ],
        [
            'key'   => 'job-sites',
            'label' => __('Job Sites'),
            'rows'  => $this->jobSites,
        ],
    ];
    $rowIndex = -1;
@endphp

<div class="relative"
     x-data="{
         open: false,
         active: -1,
         items() {
             return this.$refs.list ? Array.from(this.$refs.list.querySelectorAll('[data-result]')) : [];
         },
         move(step) {
             const items = this.items();
             if (! items.length) { return; }
             this.open = true;
             this.active = (this.active + step + items.length) % items.length;
             items[this.active].scrollIntoView({ block: 'nearest' });
         },
         choose() {
             const items = this.items();
             const target = items[this.active] ?? items[0];
             if (target) { target.click(); }
         },
         close() {
             this.open = false;
             this.active = -1;
         },
     }"
     @click.away="close()"
     @keydown.escape.window="close()">

    <label for="header-search" class="sr-only">{{ __('Search projects and job sites') }}</label>

    <input id="header-search"
           x-ref="input"
           type="search"
           autocomplete="off"
           spellcheck="false"
           wire:model.live.debounce.300ms="search"
           @focus="open = $event.target.value.trim().length >= {{ \App\Livewire\Shared\HeaderSearch::MIN_LENGTH }}"
           @input="open = $event.target.value.trim().length >= {{ \App\Livewire\Shared\HeaderSearch::MIN_LENGTH }}; active = -1"
           @keydown.arrow-down.prevent="move(1)"
           @keydown.arrow-up.prevent="move(-1)"
           @keydown.enter.prevent="choose()"
           placeholder="{{ __('Search projects or job sites...') }}"
           class="w-64 pl-10 pr-9 py-2 text-sm bg-slate-100 dark:bg-slate-700 border border-transparent rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] dark:text-white dark:placeholder-slate-400 [&::-webkit-search-cancel-button]:hidden">

    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
        <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
    </div>

    {{-- Spinner while the debounced request is in flight, clear button otherwise --}}
    <div wire:loading.delay.shortest wire:target="search"
         class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
        <svg class="w-4 h-4 animate-spin text-[#3F5189] dark:text-slate-300" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    </div>

    @if($search !== '')
        <button type="button"
                wire:loading.remove.delay.shortest
                wire:target="search"
                wire:click="clearSearch"
                @click="close(); $refs.input.focus()"
                aria-label="{{ __('Clear search') }}"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    @endif

    <!-- Results Dropdown -->
    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 top-full z-50 mt-1 w-96 max-w-[calc(100vw-3rem)] bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden">

        @if($this->hasResults)
            <div x-ref="list" class="max-h-80 overflow-y-auto">
                @foreach($groups as $group)
                    @continue($group['rows']->isEmpty())

                    <div class="px-4 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                        {{ $group['label'] }}
                        <span class="font-normal normal-case tracking-normal">({{ $group['rows']->count() }})</span>
                    </div>

                    <ul class="pb-1">
                        @foreach($group['rows'] as $row)
                            @php
                                $rowIndex++;
                                $isProject = $group['key'] === 'projects';
                                $title     = $isProject ? $row->project_name : $row->job_site_name;
                                $parent    = $isProject ? $row->client?->company_name : $row->project?->project_name;
                                $address   = collect([$row->street, $row->city, $row->state])->filter()->implode(', ');
                                $url       = $isProject
                                    ? route('projects.overview', $row->id)
                                    : route('jobsites.overview', $row->id);
                                $status    = $row->status;
                            @endphp
                            <li>
                                <a href="{{ $url }}"
                                   data-result
                                   @mouseenter="active = {{ $rowIndex }}"
                                   :class="active === {{ $rowIndex }} ? 'bg-slate-100 dark:bg-slate-700' : ''"
                                   class="flex items-start gap-3 px-4 py-2.5 hover:bg-slate-100 dark:hover:bg-slate-700">
                                    <span @class([
                                        'mt-1.5 w-2 h-2 rounded-full shrink-0',
                                        'bg-blue-500'   => $status?->color() === 'blue',
                                        'bg-orange-500' => $status?->color() === 'orange',
                                        'bg-green-500'  => $status?->color() === 'green',
                                        'bg-red-500'    => $status?->color() === 'red',
                                        'bg-slate-400'  => in_array($status?->color(), [null, 'gray'], true),
                                    ])></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-sm text-slate-800 dark:text-white truncate">
                                            {{ $title }}
                                        </span>
                                        @if($parent)
                                            <span class="block text-xs text-slate-600 dark:text-slate-300 truncate">
                                                {{ $parent }}
                                            </span>
                                        @endif
                                        @if($address !== '')
                                            <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">
                                                {{ $address }}
                                            </span>
                                        @endif
                                    </span>
                                    @if($status)
                                        <span class="shrink-0 text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                            {{ __($status->label()) }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>

            <div class="flex items-center justify-between gap-2 px-4 py-2 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    {{ __('↑↓ to browse · ↵ to open · Esc to close') }}
                </span>
                <a href="{{ route('projects.index', ['search' => $search]) }}"
                   class="text-xs font-medium text-[#3F5189] dark:text-indigo-300 hover:underline whitespace-nowrap">
                    {{ __('See all matches') }}
                </a>
            </div>
        @elseif($this->isSearching)
            <div class="px-4 py-5 text-center">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    {{ __('No matches for ":term"', ['term' => $search]) }}
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Searches project and job site names, clients, contacts and addresses.') }}
                </p>
                <a href="{{ route('projects.index') }}"
                   class="mt-3 inline-block text-xs font-medium text-[#3F5189] dark:text-indigo-300 hover:underline">
                    {{ __('Browse all projects') }}
                </a>
            </div>
        @else
            <div class="px-4 py-5 text-center text-xs text-slate-500 dark:text-slate-400">
                {{ __('Type at least :count characters to search.', ['count' => \App\Livewire\Shared\HeaderSearch::MIN_LENGTH]) }}
            </div>
        @endif
    </div>
</div>
