<div>
    @php
        $field = 'px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';

        $tabs = [
            'owned' => __('I own'),
            'assigned' => __('Assigned to me'),
            'raised' => __('I raised'),
        ];

        $groupLabels = [
            'overdue' => ['label' => __('Overdue'), 'class' => 'text-red-600 dark:text-red-400'],
            'awaiting' => ['label' => __('Awaiting confirmation'), 'class' => 'text-amber-600 dark:text-amber-400'],
            'this_week' => ['label' => __('Due this week'), 'class' => 'text-slate-700 dark:text-slate-200'],
            'later' => ['label' => __('Later'), 'class' => 'text-slate-700 dark:text-slate-200'],
            'no_date' => ['label' => __('No due date'), 'class' => 'text-slate-500 dark:text-slate-400'],
            'closed' => ['label' => __('Closed'), 'class' => 'text-slate-500 dark:text-slate-400'],
        ];
    @endphp

    <!-- Page header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('My Tasks') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Everything you own, are helping with, or asked for.') }}
            </p>
        </div>
        <x-ui.button variant="primary" icon="plus" wire:click="openTaskForm">{{ __('New Task') }}</x-ui.button>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- What is on my plate, whatever the filters say -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Open Tasks') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ $this->stats['open'] }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-sm text-white/80">{{ __('owned by or assigned to you') }}</p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Overdue') }}</p>
                    <p class="text-2xl font-bold mt-1 {{ $this->stats['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                        {{ $this->stats['overdue'] }}
                    </p>
                </div>
                <div class="bg-red-100 dark:bg-red-900/20 rounded-full p-3">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Due This Week') }}</p>
                    <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $this->stats['due_this_week'] }}</p>
                </div>
                <div class="bg-slate-100 dark:bg-slate-700 rounded-full p-3">
                    <svg class="h-6 w-6 text-slate-500 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Awaiting Confirmation') }}</p>
                    <p class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ $this->stats['awaiting_me'] }}</p>
                </div>
                <div class="bg-amber-100 dark:bg-amber-900/20 rounded-full p-3">
                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">{{ __('ready, waiting on your word') }}</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-700 mb-4">
        <nav class="-mb-px flex gap-6 overflow-x-auto">
            @foreach($tabs as $key => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors
                        {{ $tab === $key
                            ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-200' }}">
                    {{ $label }}
                    <span class="ml-1.5 rounded-full px-2 py-0.5 text-xs
                        {{ $tab === $key ? 'bg-[#3F5189] text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                        {{ $this->tabCounts[$key] }}
                    </span>
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row flex-wrap gap-3 mb-6">
        <div class="relative flex-1 min-w-[14rem] max-w-md">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search title, description or #number...') }}"
                   class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
            <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <select wire:model.live="statusFilter" class="{{ $field }}">
            <option value="">{{ __('All Statuses') }}</option>
            <option value="open">{{ __('Task status: open') }}</option>
            <option value="in_progress">{{ __('Task status: in progress') }}</option>
            <option value="blocked">{{ __('Task status: blocked') }}</option>
            <option value="ready">{{ __('Task status: awaiting confirmation') }}</option>
            <option value="completed">{{ __('Task status: completed') }}</option>
            <option value="cancelled">{{ __('Task status: cancelled') }}</option>
        </select>

        <select wire:model.live="priorityFilter" class="{{ $field }}">
            <option value="">{{ __('All Priorities') }}</option>
            <option value="urgent">{{ __('Task priority: urgent') }}</option>
            <option value="high">{{ __('Task priority: high') }}</option>
            <option value="normal">{{ __('Task priority: normal') }}</option>
            <option value="low">{{ __('Task priority: low') }}</option>
        </select>

        @if($this->filterProjects->isNotEmpty())
            <select wire:model.live="projectFilter" class="{{ $field }}">
                <option value="">{{ __('All Projects') }}</option>
                @foreach($this->filterProjects as $project)
                    <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                @endforeach
            </select>
        @endif

        <div class="flex items-center">
            <x-ui.toggle wire:model.live="showClosed" :checked="$showClosed" label="{{ __('Show closed') }}" />
        </div>

        @if($this->hasFilters())
            <button type="button" wire:click="clearFilters" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                {{ __('Clear filters') }}
            </button>
        @endif
    </div>

    <!-- The list -->
    @if($this->groups->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.5 21h9a2.5 2.5 0 002.5-2.5v-13A2.5 2.5 0 0016.5 3h-9A2.5 2.5 0 005 5.5v13A2.5 2.5 0 007.5 21z"/>
            </svg>

            @if($this->hasFilters())
                <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('Nothing matches these filters') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Try widening the search, or clear the filters to see everything.') }}</p>
                <div class="mt-4">
                    <x-ui.button variant="secondary" size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
                </div>
            @else
                <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">
                    {{ $tab === 'owned' ? __('Nothing on your plate') : ($tab === 'assigned' ? __('You are not helping with anything right now') : __('You have not raised anything yet')) }}
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Tasks raised in a meeting land here too, as soon as you are made owner or assignee.') }}
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

    @include('livewire.task.partials.form-modal')
    @include('livewire.task.partials.detail-modal')
    @include('livewire.task.partials.reason-modal')
</div>
