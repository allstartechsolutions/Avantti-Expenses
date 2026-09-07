@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $tab = fn ($key) => $activeTab === $key
        ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]'
        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300';
    $canLink = auth()->user()->can('people.link');
@endphp
<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('People') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('The same person across several subcontractors — every company they have worked through, every tax id they presented, every contract they were on.') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button variant="secondary" href="{{ route('subcontractors.index') }}" icon="arrow-left">{{ __('Subcontractors') }}</x-ui.button>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">{{ session('error') }}</div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('People linked') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $counts['people'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Each known at two or more companies') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('With more than one tax id') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $counts['multiTaxId'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ $counts['multiTaxId'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Presented a different number to different companies') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Suggested links') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $counts['suggestions'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Records at different companies sharing a tax id, phone or e-mail') }}</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-700 mb-6">
        <nav class="-mb-px flex flex-wrap gap-x-8" aria-label="{{ __('Sections') }}">
            <button type="button" wire:click="setActiveTab('people')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('people') }}">
                {{ __('People') }}
                <span class="ml-2 py-0.5 px-2 rounded-full text-xs bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $counts['people'] }}</span>
            </button>
            <button type="button" wire:click="setActiveTab('suggestions')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('suggestions') }}">
                {{ __('Suggested links') }}
                @if($counts['suggestions'] > 0)
                    <span class="ml-2 py-0.5 px-2 rounded-full text-xs bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ $counts['suggestions'] }}</span>
                @endif
            </button>
        </nav>
    </div>

    @if($activeTab === 'people')
        <!-- Search -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6 p-6">
            <label for="search" class="sr-only">{{ __('Search people') }}</label>
            <div class="relative max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="search" wire:model.live.debounce.300ms="search" class="block w-full pl-10 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400" placeholder="{{ __('Search by name, company, phone, e-mail or tax id…') }}">
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            @if($people->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="{{ $th }}">{{ __('Person') }}</th>
                                <th class="{{ $th }}">{{ __('Companies') }}</th>
                                <th class="{{ $th }}">{{ __('Tax IDs') }}</th>
                                <th class="{{ $th }}">{{ __('Contracts') }}</th>
                                <th class="{{ $th }}">{{ __('Last linked') }}</th>
                                <th class="{{ $th }} text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($people as $person)
                                @php
                                    $taxIds = $person->distinctTaxIds();
                                    $lastLink = $person->employees->sortByDesc('linked_at')->first();
                                    $current = $person->employees->filter->isCurrent();
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('people.show', $person) }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $person->name }}</a>
                                        @if($person->employees->pluck('name')->unique()->count() > 1)
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Also as :names', ['names' => $person->employees->pluck('name')->unique()->reject(fn ($n) => $n === $person->name)->join(', ')]) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($person->employees->sortBy(fn ($e) => $e->subcontractor?->company_name) as $row)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $row->isCurrent() ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}" title="{{ $row->isCurrent() ? __('Current') : __('Former') }}">
                                                    {{ $row->subcontractor?->company_name ?? __('Deleted company') }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap">
                                        @if($taxIds->isEmpty())
                                            <span class="text-slate-500 dark:text-slate-400">—</span>
                                        @elseif($taxIds->count() === 1)
                                            <span class="font-mono text-slate-900 dark:text-white">{{ $taxIds->first() }}</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ trans_choice(':count tax id|:count different tax ids', $taxIds->count(), ['count' => $taxIds->count()]) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ $person->contracts_count }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                        @if($lastLink?->linked_at)
                                            {{ $lastLink->linked_at->appDate() }}
                                            @if($lastLink->linkedBy)<span class="block text-xs">{{ __('by :name', ['name' => $lastLink->linkedBy->name]) }}</span>@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <x-ui.view-edit-buttons :viewRoute="route('people.show', $person)" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($people->hasPages())
                    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">{{ $people->links() }}</div>
                @endif
            @else
                <div class="text-center py-12 px-6">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    @if(trim($search) !== '')
                        <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('Nobody matches ":term"', ['term' => $search]) }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Only people linked across two or more companies are listed here. An employee known at one company is on that company\'s page.') }}</p>
                    @else
                        <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('Nobody has been linked yet') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('A person appears here once two employee records at different companies are linked as the same human. Do it from a subcontractor\'s Employees tab, or from the suggestions.') }}</p>
                        @if($counts['suggestions'] > 0)
                            <div class="mt-6">
                                <x-ui.button variant="primary" wire:click="setActiveTab('suggestions')">{{ trans_choice('Review :count suggestion|Review :count suggestions', $counts['suggestions'], ['count' => $counts['suggestions']]) }}</x-ui.button>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    @else
        <!-- Suggested links -->
        @if($suggestions->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-8 text-center">
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('No suggestions right now') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('No two records at different companies share a tax id, phone or e-mail without already being linked. A shared name alone is offered on the company page when the employee is typed, not here.') }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($suggestions as $group)
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="text-sm">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $group['reason'] === 'tax_id' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">{{ \App\Models\SubcontractorEmployee::matchReasonLabel($group['reason']) }}</span>
                                <span class="ml-2 font-mono text-slate-900 dark:text-white">{{ $group['rows']->first()->{$group['reason']} }}</span>
                                <span class="ml-2 text-slate-500 dark:text-slate-400">{{ trans_choice(':count record|:count records', $group['rows']->count(), ['count' => $group['rows']->count()]) }} · {{ trans_choice(':count company|:count companies', $group['rows']->pluck('subcontractor_id')->unique()->count(), ['count' => $group['rows']->pluck('subcontractor_id')->unique()->count()]) }}</span>
                            </div>
                            @if($canLink)
                                <x-ui.button variant="primary" size="sm" wire:click="linkGroup({{ json_encode($group['rows']->pluck('id')->all()) }}, {{ json_encode(__('Matched on :reason on the People page', ['reason' => \App\Models\SubcontractorEmployee::matchReasonLabel($group['reason'])])) }})" wire:confirm="{{ __('Link all of these records as one person?') }}">{{ __('Link all as one person') }}</x-ui.button>
                            @endif
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead>
                                    <tr>
                                        <th class="{{ $th }}">{{ __('Name') }}</th>
                                        <th class="{{ $th }}">{{ __('Company') }}</th>
                                        <th class="{{ $th }}">{{ __('Title') }}</th>
                                        <th class="{{ $th }}">{{ __('Phone') }}</th>
                                        <th class="{{ $th }}">{{ __('Email') }}</th>
                                        <th class="{{ $th }}">{{ __('Tax ID') }}</th>
                                        <th class="{{ $th }}">{{ __('Already linked to') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($group['rows'] as $row)
                                        <tr>
                                            <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white whitespace-nowrap">{{ $row->name }}</td>
                                            <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">
                                                @if($row->subcontractor)
                                                    <a href="{{ route('subcontractors.show', $row->subcontractor) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $row->subcontractor->company_name }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $row->title ?? '—' }}</td>
                                            <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $row->formatted_phone ?? '—' }}</td>
                                            <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $row->email ?? '—' }}</td>
                                            <td class="px-6 py-3 text-sm font-mono text-slate-900 dark:text-white whitespace-nowrap">{{ $row->tax_id ?? '—' }}</td>
                                            <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">
                                                @if($row->person)
                                                    <a href="{{ route('people.show', $row->person) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $row->person->name }}</a>
                                                    <span class="block text-xs">{{ $row->siblings()->map(fn ($s) => $s->subcontractor?->company_name)->filter()->join(', ') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
