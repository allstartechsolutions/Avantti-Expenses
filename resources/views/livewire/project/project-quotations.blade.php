<x-project-layout :project="$project" active="quotations" title="{{ __('Quotations') }}">
    <div class="space-y-6">
        @if(session('error'))
            <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                {{ session('error') }}
            </div>
        @endif

        @if(session('message'))
            <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                {{ session('message') }}
            </div>
        @endif

        <!-- Search, filters and the add button -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 flex flex-col sm:flex-row flex-wrap gap-4">
                <div class="relative max-w-md">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Search number, title, item, vendor...') }}"
                        class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <select wire:model.live="locationFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('All Locations') }}</option>
                    <option value="project">{{ __('Project (General)') }}</option>
                    @foreach($jobSites as $js)
                        <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="open">{{ __('Open') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="sent">{{ __('Sent to Vendors') }}</option>
                    <option value="comparing">{{ __('Comparing') }}</option>
                    <option value="negotiating">{{ __('Negotiating') }}</option>
                    <option value="awarded">{{ __('Awarded') }}</option>
                    <option value="converted">{{ __('Converted') }}</option>
                    <option value="cancelled">{{ __('Cancelled') }}</option>
                </select>
                <select wire:model.live="typeFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('All Types') }}</option>
                    <option value="material">{{ __('Material') }}</option>
                    <option value="service">{{ __('Service') }}</option>
                </select>
                @if($this->hasFilters())
                    <button type="button" wire:click="clearFilters" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                        {{ __('Clear filters') }}
                    </button>
                @endif
            </div>
            <x-ui.button variant="primary" icon="plus" wire:click="openAddModal">
                {{ __('New Quotation') }}
            </x-ui.button>
        </div>

        <!-- Approved requisitions waiting to be quoted -->
        @if($quotableRequisitions->where('status', 'approved')->count() > 0)
            <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-5">
                <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('Approved requisitions waiting to be quoted') }}</h3>
                <ul class="mt-3 space-y-2">
                    @foreach($quotableRequisitions->where('status', 'approved') as $requisition)
                        <li class="flex flex-wrap items-center justify-between gap-3">
                            <span class="text-sm text-amber-900 dark:text-amber-100">
                                <span class="font-medium">{{ $requisition->requisition_number }}</span> — {{ $requisition->title }}
                            </span>
                            <x-ui.button variant="outline" size="sm" wire:click="openAddFromRequisition({{ $requisition->id }})">
                                {{ __('Quote it') }}
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-white/80">{{ __('Quotations') }}</p>
                        <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                    </div>
                    <div class="bg-white/10 rounded-full p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                        </svg>
                    </div>
                </div>
                <p class="mt-2 text-sm text-white/80">{{ __(':count awarded', ['count' => $stats['awarded']]) }}</p>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Awaiting Proposals') }}</p>
                        <p class="text-2xl font-bold mt-1 text-blue-600 dark:text-blue-400">{{ $stats['awaiting'] }}</p>
                    </div>
                    <div class="bg-blue-100 dark:bg-blue-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Past the Deadline') }}</p>
                        <p class="text-2xl font-bold mt-1 text-red-600 dark:text-red-400">{{ $stats['overdue'] }}</p>
                    </div>
                    <div class="bg-red-100 dark:bg-red-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Awarded') }}</p>
                        <p class="text-2xl font-bold mt-1 text-green-600 dark:text-green-400">{{ $stats['awarded'] }}</p>
                    </div>
                    <div class="bg-green-100 dark:bg-green-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <x-quotation-table :quotations="$quotations" :hasFilters="$this->hasFilters()" />
    </div>

    @include('livewire.quotation.partials.form-modal', [
        'contextName' => $project->project_name,
        'showJobSitePicker' => true,
    ])

    @include('livewire.quotation.partials.send-modal')

    @include('livewire.quotation.partials.proposal-modal')

    @include('livewire.quotation.partials.comparison-modal')

    @include('livewire.quotation.partials.award-modal')

    @include('livewire.quotation.partials.view-modal', [
        'canReview' => auth()->user()?->canReviewRequisitions() ?? false,
    ])
</x-project-layout>
