<div>
    @php
        $field = 'px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
        $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
        $stats = $this->stats;
    @endphp

    <!-- Page header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Meetings') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Every minute held, and every meeting being prepared.') }}
            </p>
        </div>
        @if($this->canManage())
            <div class="flex items-center gap-3">
                <x-ui.button variant="secondary" href="{{ route('meeting-series.index') }}">{{ __('Meeting Series') }}</x-ui.button>
                <x-ui.button variant="primary" icon="plus" href="{{ route('meetings.create') }}">{{ __('New Meeting') }}</x-ui.button>
            </div>
        @endif
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
            <p class="text-sm font-medium text-white/80">{{ __('Next Meeting') }}</p>
            @if($stats['next'])
                <p class="text-2xl font-bold mt-1">{{ $stats['next']->meeting_date->format($dateFormat) }}</p>
                <p class="mt-2 text-sm text-white/80 truncate">{{ $stats['next']->number }} — {{ $stats['next']->title }}</p>
            @else
                <p class="text-2xl font-bold mt-1">—</p>
                <p class="mt-2 text-sm text-white/80">{{ __('Nothing scheduled') }}</p>
            @endif
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('In Preparation') }}</p>
            <p class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ $stats['drafts'] }}</p>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">{{ __('drafts not yet published') }}</p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Published Minutes') }}</p>
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $stats['published'] }}</p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Active Series') }}</p>
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $stats['series'] }}</p>
            @if($this->canManage())
                <a href="{{ route('meeting-series.index') }}" class="mt-2 inline-block text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('Manage series') }}</a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row flex-wrap gap-3 mb-6">
        <div class="relative flex-1 min-w-[14rem] max-w-md">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search number, title or location...') }}"
                   class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
            <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <select wire:model.live="seriesFilter" class="{{ $field }}">
            <option value="">{{ __('All Series') }}</option>
            @foreach($this->seriesOptions as $series)
                <option value="{{ $series->id }}">{{ $series->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter" class="{{ $field }}">
            <option value="">{{ __('All Statuses') }}</option>
            <option value="draft">{{ __('Draft') }}</option>
            <option value="published">{{ __('Published') }}</option>
            <option value="cancelled">{{ __('Cancelled') }}</option>
        </select>

        <input type="date" wire:model.live="fromDate" class="{{ $field }}" title="{{ __('From') }}">
        <input type="date" wire:model.live="toDate" class="{{ $field }}" title="{{ __('To') }}">

        @if($this->hasFilters())
            <button type="button" wire:click="clearFilters" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                {{ __('Clear filters') }}
            </button>
        @endif
    </div>

    @if($meetings->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>

            @if($this->hasFilters())
                <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('No meetings match these filters') }}</h3>
                <div class="mt-4">
                    <x-ui.button variant="secondary" size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
                </div>
            @else
                <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('No meetings yet') }}</h3>
                <p class="mx-auto mt-1 max-w-lg text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Set up a series first — a meeting created inside one starts with its people, its projects and its open items already there.') }}
                </p>
                @if($this->canManage())
                    <div class="mt-4 flex items-center justify-center gap-3">
                        <x-ui.button variant="secondary" size="sm" href="{{ route('meeting-series.index') }}">{{ __('Meeting Series') }}</x-ui.button>
                        <x-ui.button variant="primary" size="sm" icon="plus" href="{{ route('meetings.create') }}">{{ __('New Meeting') }}</x-ui.button>
                    </div>
                @endif
            @endif
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Minute') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Chair') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Attendance') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Agenda') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($meetings as $meeting)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40" wire:key="meeting-{{ $meeting->id }}">
                                <td class="px-6 py-4">
                                    <p class="font-mono text-xs text-slate-400 dark:text-slate-500">{{ $meeting->number }}</p>
                                    <a href="{{ route('meetings.show', $meeting) }}" class="font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                        {{ $meeting->title }}
                                    </a>
                                    @if($meeting->series)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $meeting->series->name }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                    {{ $meeting->meeting_date->format($dateFormat) }}
                                    @if($meeting->started_at)
                                        <span class="block text-xs text-slate-400">{{ substr($meeting->started_at, 0, 5) }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $meeting->chair?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                    {{ __(':present of :invited', ['present' => $meeting->present_count, 'invited' => $meeting->invited_count]) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                    {{ $meeting->items_count > 0
                                        ? trans_choice(':count item|:count items', $meeting->items_count, ['count' => $meeting->items_count])
                                        : __('not built yet') }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ $meeting->status === 'published'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : ($meeting->status === 'cancelled'
                                                ? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300') }}">
                                        {{ $meeting->getStatusLabel() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-ui.button variant="secondary" size="sm" icon="eye" href="{{ route('meetings.show', $meeting) }}">
                                            {{ $meeting->isPublished() ? __('Minute') : __('Open Meeting') }}
                                        </x-ui.button>
                                        @if($meeting->isDraft() && $this->canManage())
                                            <x-ui.button variant="primary" size="sm" href="{{ route('meetings.agenda', $meeting) }}">
                                                {{ __('Agenda') }}
                                            </x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($meetings->hasPages())
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                    {{ $meetings->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
