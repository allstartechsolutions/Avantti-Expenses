{{--
    The task list for one location. Included by both the project and the job
    site page so the two levels cannot drift apart
    (docs/project-jobsite-parity-rule.md).

    Expects: $contextName, $showJobSiteFilter
--}}
@php
    $field = 'px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';

    $groupLabels = [
        'overdue' => ['label' => __('Overdue'), 'class' => 'text-red-600 dark:text-red-400'],
        'awaiting' => ['label' => __('Awaiting confirmation'), 'class' => 'text-amber-600 dark:text-amber-400'],
        'this_week' => ['label' => __('Due this week'), 'class' => 'text-slate-700 dark:text-slate-200'],
        'later' => ['label' => __('Later'), 'class' => 'text-slate-700 dark:text-slate-200'],
        'no_date' => ['label' => __('No due date'), 'class' => 'text-slate-500 dark:text-slate-400'],
        'closed' => ['label' => __('Closed'), 'class' => 'text-slate-500 dark:text-slate-400'],
    ];

    $stats = $this->stats;
@endphp

<div class="space-y-6">
    @if (session()->has('message'))
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Filters and the add button -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="flex-1 flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="relative max-w-md">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Search title, description or #number...') }}"
                       class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <select wire:model.live="statusFilter" class="{{ $field }}">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="open">{{ __('Open') }}</option>
                <option value="in_progress">{{ __('In Progress') }}</option>
                <option value="blocked">{{ __('Blocked') }}</option>
                <option value="ready">{{ __('Awaiting Confirmation') }}</option>
                <option value="completed">{{ __('Completed') }}</option>
                <option value="cancelled">{{ __('Cancelled') }}</option>
            </select>

            <select wire:model.live="priorityFilter" class="{{ $field }}">
                <option value="">{{ __('All Priorities') }}</option>
                <option value="urgent">{{ __('Urgent') }}</option>
                <option value="high">{{ __('High') }}</option>
                <option value="normal">{{ __('Normal') }}</option>
                <option value="low">{{ __('Low') }}</option>
            </select>

            @if($this->taskOwners->isNotEmpty())
                <select wire:model.live="ownerFilter" class="{{ $field }}">
                    <option value="">{{ __('All Owners') }}</option>
                    @foreach($this->taskOwners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                    @endforeach
                </select>
            @endif

            @if($showJobSiteFilter && $this->scopeJobSites->isNotEmpty())
                <select wire:model.live="jobSiteFilter" class="{{ $field }}">
                    <option value="">{{ __('Project and all job sites') }}</option>
                    <option value="project">{{ __('The project as a whole') }}</option>
                    @foreach($this->scopeJobSites as $site)
                        <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                    @endforeach
                </select>
            @endif

            {{-- The distinction the whole module rests on, made visible. --}}
            <select wire:model.live="trackingFilter" class="{{ $field }}">
                <option value="">{{ __('On and off the agenda') }}</option>
                <option value="meeting">{{ __('Tracked in meetings') }}</option>
                <option value="direct">{{ __('Not on any agenda') }}</option>
            </select>

            <div class="flex items-center">
                <x-ui.toggle wire:model.live="showClosed" :checked="$showClosed" label="{{ __('Show closed') }}" />
            </div>

            @if($this->hasTaskFilters())
                <button type="button" wire:click="clearTaskFilters" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                    {{ __('Clear filters') }}
                </button>
            @endif
        </div>

        <x-ui.button variant="primary" icon="plus" wire:click="openTaskForm">{{ __('New Task') }}</x-ui.button>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Open Tasks') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['open'] }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-sm text-white/80 truncate">{{ $contextName }}</p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Overdue') }}</p>
                    <p class="text-2xl font-bold mt-1 {{ $stats['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                        {{ $stats['overdue'] }}
                    </p>
                </div>
                <div class="bg-red-100 dark:bg-red-900/20 rounded-full p-3">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            @if($stats['oldest'])
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500 truncate">
                    {{ __('Oldest open: :code, :days days', [
                        'code' => $stats['oldest']->code(),
                        'days' => (int) $stats['oldest']->created_at->diffInDays(now()),
                    ]) }}
                </p>
            @endif
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Awaiting Confirmation') }}</p>
                    <p class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ $stats['awaiting'] }}</p>
                </div>
                <div class="bg-amber-100 dark:bg-amber-900/20 rounded-full p-3">
                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            @if($stats['next_due'])
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                    {{ __('Next due :date', ['date' => \Illuminate\Support\Carbon::parse($stats['next_due'])->format($dateFormat)]) }}
                </p>
            @endif
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Not On Any Agenda') }}</p>
                    <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $stats['off_agenda'] }}</p>
                </div>
                <div class="bg-slate-100 dark:bg-slate-700 rounded-full p-3">
                    <svg class="h-6 w-6 text-slate-500 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                {{ __('open, and no meeting has discussed them') }}
            </p>
        </div>
    </div>

    <!-- The list -->
    @if($this->groups->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.5 21h9a2.5 2.5 0 002.5-2.5v-13A2.5 2.5 0 0016.5 3h-9A2.5 2.5 0 005 5.5v13A2.5 2.5 0 007.5 21z"/>
            </svg>

            @if($this->hasTaskFilters())
                <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('Nothing matches these filters') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Try widening the search, or clear the filters to see everything.') }}</p>
                <div class="mt-4">
                    <x-ui.button variant="secondary" size="sm" wire:click="clearTaskFilters">{{ __('Clear filters') }}</x-ui.button>
                </div>
            @else
                <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('No tasks here yet') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Raise one here, or it will appear when a meeting raises it against :name.', ['name' => $contextName]) }}
                </p>
                <div class="mt-4">
                    <x-ui.button variant="primary" size="sm" icon="plus" wire:click="openTaskForm">{{ __('New Task') }}</x-ui.button>
                </div>
            @endif
        </div>
    @else
        <div class="space-y-6">
            @foreach($this->groups as $key => $tasks)
                <div>
                    <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider {{ $groupLabels[$key]['class'] }}">
                        {{ $groupLabels[$key]['label'] }}
                        <span class="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                            {{ $tasks->count() }}
                        </span>
                    </h2>

                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 divide-y divide-slate-200 dark:divide-slate-700 overflow-hidden">
                        @foreach($tasks as $task)
                            @include('livewire.task.partials.row', ['task' => $task])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@include('livewire.task.partials.form-modal')
@include('livewire.task.partials.detail-modal')
@include('livewire.task.partials.reason-modal')
