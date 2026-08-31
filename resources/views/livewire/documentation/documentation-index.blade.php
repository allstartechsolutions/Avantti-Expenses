<div>
    @php
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
        $counts = $this->counts;
    @endphp

    <!-- Page header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Documentation') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('How the system works, and how this company works.') }}
            </p>
        </div>
        @if($this->canWrite())
            <x-ui.button variant="primary" icon="plus" href="{{ route('documentation.create') }}">{{ __('Write a Guide') }}</x-ui.button>
        @endif
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Search -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="relative flex-1 max-w-xl">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search the guides...') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
            <svg class="absolute left-3 top-3 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ trans_choice(':count guide|:count guides', $counts['total'], ['count' => $counts['total']]) }}
            @if($this->canWrite() && $counts['drafts'] > 0)
                · <span class="text-amber-600 dark:text-amber-400">{{ trans_choice(':count draft|:count drafts', $counts['drafts'], ['count' => $counts['drafts']]) }}</span>
            @endif
        </p>
    </div>

    @if($this->groups->isEmpty())
        <div class="{{ $card }} p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            @if($search !== '')
                <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('Nothing matches ":term"', ['term' => $search]) }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Try a shorter word, or clear the search to see everything.') }}</p>
            @else
                <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('No guides yet') }}</h3>
            @endif
        </div>
    @else
        <div class="space-y-8">
            @foreach($this->groups as $key => $group)
                <div wire:key="doc-group-{{ $key }}">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ $group['label'] }}
                        <span class="ml-1 text-slate-400 dark:text-slate-500">({{ $group['entries']->count() }})</span>
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($group['entries'] as $entry)
                            <a href="{{ route('documentation.show', $entry['slug']) }}" wire:navigate
                               class="{{ $card }} group flex flex-col p-5 transition-all hover:border-[#3F5189] hover:shadow-md dark:hover:border-[#4A5A96]">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-slate-900 dark:text-white group-hover:text-[#3F5189] dark:group-hover:text-[#4A5A96]">
                                        {{ $entry['title'] }}
                                    </h3>
                                    @unless($entry['is_published'])
                                        <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                            {{ __('Draft') }}
                                        </span>
                                    @endunless
                                </div>

                                @if($entry['summary'])
                                    <p class="mt-2 flex-1 text-sm text-slate-500 dark:text-slate-400">{{ $entry['summary'] }}</p>
                                @endif

                                <p class="mt-3 flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                                    @if($entry['source'] === 'shipped')
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 dark:bg-slate-700">{{ __('Product guide') }}</span>
                                    @else
                                        <span class="rounded bg-[#3F5189]/10 px-1.5 py-0.5 text-[#3F5189] dark:text-[#4A5A96]">{{ __('Written here') }}</span>
                                    @endif
                                    <span>{{ __('updated :date', ['date' => $entry['updated_at']?->appDate()]) }}</span>
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
