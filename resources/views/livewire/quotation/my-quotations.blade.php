<div>
    @php
        $field = 'px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';

        $tabs = ['to_start' => __('To start'), 'in_progress' => __('In progress')];

        if ($this->canSeeUnassigned()) {
            $tabs['unassigned'] = __('Unassigned');
        }
    @endphp

    <!-- Page header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('My Quotations') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            {{ __('What you have been asked to price, and the rounds you are running.') }}
        </p>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- What is on my plate, whatever the filters say -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('To Start') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ $this->stats['to_start'] }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-sm text-white/80">{{ __('approved and waiting for a round') }}</p>
        </div>

        <div class="{{ $card }} p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Waiting Over a Week') }}</p>
                    <p class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ $this->stats['waiting_a_week'] }}</p>
                </div>
                <div class="bg-amber-100 dark:bg-amber-900/20 rounded-full p-3">
                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">{{ __('these are the ones being chased') }}</p>
        </div>

        <div class="{{ $card }} p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Rounds In Progress') }}</p>
                    <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $this->stats['in_progress'] }}</p>
                </div>
                <div class="bg-slate-100 dark:bg-slate-700 rounded-full p-3">
                    <svg class="h-6 w-6 text-slate-500 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">{{ __('owned by or shared with you') }}</p>
        </div>

        <div class="{{ $card }} p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Responses Past Due') }}</p>
                    <p class="text-2xl font-bold mt-1 text-red-600 dark:text-red-400">{{ $this->stats['responses_overdue'] }}</p>
                </div>
                <div class="bg-red-100 dark:bg-red-900/20 rounded-full p-3">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">{{ __('vendors who have not come back') }}</p>
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
                   placeholder="{{ __('Search title or #number...') }}"
                   class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
            <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        @if($this->filterProjects->isNotEmpty())
            <select wire:model.live="projectFilter" class="{{ $field }}">
                <option value="">{{ __('All Projects') }}</option>
                @foreach($this->filterProjects as $project)
                    <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                @endforeach
            </select>
        @endif

        @if($this->hasFilters())
            <button type="button" wire:click="clearFilters" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                {{ __('Clear filters') }}
            </button>
        @endif
    </div>

    <!-- The list -->
    @if($this->rows->isEmpty())
        <div class="{{ $card }} px-6 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="mt-4 text-base font-medium text-slate-900 dark:text-white">
                @if($this->hasFilters())
                    {{ __('Nothing matches those filters.') }}
                @elseif($tab === 'to_start')
                    {{ __('Nothing is waiting on you.') }}
                @elseif($tab === 'in_progress')
                    {{ __('You are not running any rounds at the moment.') }}
                @else
                    {{ __('Every approved requisition has somebody on it.') }}
                @endif
            </p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if($this->hasFilters())
                    {{ __('Clear the filters to see everything on your list.') }}
                @elseif($tab === 'to_start')
                    {{ __('When a requisition is approved and handed to you, it appears here and you are e-mailed.') }}
                @elseif($tab === 'in_progress')
                    {{ __('Rounds you own or are helping with show up here until they are awarded.') }}
                @else
                    {{ __('Nothing is sitting in the unassigned bucket — which is where it should be.') }}
                @endif
            </p>
        </div>
    @elseif($tab === 'in_progress')
        <div class="{{ $card }} overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Round') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Where') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Proposals') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Responses Due') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('My Part') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($this->rows as $round)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors" wire:key="round-{{ $round->id }}">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $round->title }}</div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $round->quotation_number }} &middot; {{ $round->getStatusLabel() }}
                                        &middot; {{ trans_choice(':count item|:count items', $round->items_count, ['count' => $round->items_count]) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                    {{ $round->project?->project_name }}
                                    @if($round->jobSite)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $round->jobSite->job_site_name }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="text-slate-900 dark:text-white font-medium">{{ $round->respondedCount() }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">/ {{ $round->invitedCount() }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($round->responses_due_at)
                                        <span class="{{ $round->responsesOverdue() ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-900 dark:text-white' }}">
                                            {{ $round->responses_due_at->appDate() }}
                                        </span>
                                        @if($round->responsesOverdue())
                                            <div class="text-xs text-red-600 dark:text-red-400">{{ __('Overdue') }}</div>
                                        @endif
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($round->assigned_to === auth()->id())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#3F5189]/10 text-[#3F5189] dark:bg-[#4A5A96]/20 dark:text-[#4A5A96]">
                                            {{ __('You own it') }}
                                        </span>
                                    @else
                                        <span class="text-slate-500 dark:text-slate-400">
                                            {{ __('Helping :name', ['name' => $round->assignedTo?->name ?? __('Unassigned')]) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <x-ui.button variant="secondary" size="sm" icon="eye" :href="$this->linkFor($round, 'quotation')">
                                        {{ __('Open') }}
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="{{ $card }} overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Requisition') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Where') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Needed By') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ $tab === 'unassigned' ? __('Approved') : __('Waiting') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($this->rows as $requisition)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors" wire:key="requisition-{{ $requisition->id }}">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-2">
                                        @if($requisition->priority === 'urgent')
                                            <span class="mt-0.5 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300">
                                                {{ __('Urgent') }}
                                            </span>
                                        @endif
                                        <div>
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $requisition->title }}</div>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ $requisition->requisition_number }}
                                                &middot; {{ trans_choice(':count item|:count items', $requisition->items_count, ['count' => $requisition->items_count]) }}
                                                &middot; {{ __('asked for by :name', ['name' => $requisition->getRequesterName()]) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                    {{ $requisition->project?->project_name }}
                                    @if($requisition->jobSite)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $requisition->jobSite->job_site_name }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($requisition->needed_by)
                                        <span class="{{ $requisition->isOverdue() ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-900 dark:text-white' }}">
                                            {{ $requisition->needed_by->appDate() }}
                                        </span>
                                        @if($requisition->isOverdue())
                                            <div class="text-xs text-red-600 dark:text-red-400">{{ __('Overdue') }}</div>
                                        @endif
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @php $waited = $tab === 'unassigned' ? null : $requisition->daysSinceAssigned(); @endphp
                                    @if($tab === 'unassigned')
                                        <span class="text-slate-900 dark:text-white">
                                            {{ $requisition->reviewed_at?->appDate() ?? '—' }}
                                        </span>
                                    @elseif($waited !== null)
                                        <span class="{{ $waited >= 7 ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-slate-900 dark:text-white' }}">
                                            {{ trans_choice(':count day|:count days', $waited, ['count' => $waited]) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <x-ui.button variant="{{ $tab === 'unassigned' ? 'secondary' : 'primary' }}"
                                                 size="sm"
                                                 icon="{{ $tab === 'unassigned' ? 'eye' : 'plus' }}"
                                                 :href="$this->linkFor($requisition, 'requisition')">
                                        {{ $tab === 'unassigned' ? __('Open') : __('Start the round') }}
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
