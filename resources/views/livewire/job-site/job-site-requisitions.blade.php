<x-jobsite-layout :jobSite="$jobSite" active="requisitions" title="{{ __('Requisitions') }}">
    <div class="space-y-6">
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
                        placeholder="{{ __('Search number, title, item, requester...') }}"
                        class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <select wire:model.live="statusFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="open">{{ __('Open') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="pending">{{ __('Pending Approval') }}</option>
                    <option value="approved">{{ __('Approved') }}</option>
                    <option value="quoted">{{ __('Quoted') }}</option>
                    <option value="fulfilled">{{ __('Fulfilled') }}</option>
                    <option value="rejected">{{ __('Rejected') }}</option>
                    <option value="cancelled">{{ __('Cancelled') }}</option>
                </select>
                <select wire:model.live="typeFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('All Types') }}</option>
                    <option value="material">{{ __('Material') }}</option>
                    <option value="service">{{ __('Service') }}</option>
                </select>
                <select wire:model.live="priorityFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('All Priorities') }}</option>
                    <option value="urgent">{{ __('Urgent') }}</option>
                    <option value="normal">{{ __('Normal') }}</option>
                    <option value="low">{{ __('Low') }}</option>
                </select>
                <select wire:model.live="assignmentFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('Anyone quoting') }}</option>
                    <option value="mine">{{ __('Assigned to me') }}</option>
                    <option value="unassigned">{{ __('Unassigned') }}</option>
                </select>
                @if($this->hasFilters())
                    <button type="button" wire:click="clearFilters" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                        {{ __('Clear filters') }}
                    </button>
                @endif
            </div>
            @can('requisitions.create', $jobSite)
            <x-ui.button variant="primary" icon="plus" wire:click="openAddModal">
                {{ __('Add Requisition') }}
            </x-ui.button>
            @endcan
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-white/80">{{ __('Requisitions') }}</p>
                        <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                    </div>
                    <div class="bg-white/10 rounded-full p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                </div>
                <p class="mt-2 text-sm text-white/80">{{ __(':count approved', ['count' => $stats['approved']]) }}</p>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Pending Approval') }}</p>
                        <p class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ $stats['pending'] }}</p>
                    </div>
                    <div class="bg-amber-100 dark:bg-amber-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Urgent, Still Open') }}</p>
                        <p class="text-2xl font-bold mt-1 text-red-600 dark:text-red-400">{{ $stats['urgent_open'] }}</p>
                    </div>
                    <div class="bg-red-100 dark:bg-red-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Past Needed-By Date') }}</p>
                        <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $stats['overdue'] }}</p>
                    </div>
                    <div class="bg-slate-100 dark:bg-slate-700 rounded-full p-3">
                        <svg class="h-6 w-6 text-slate-500 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <x-requisition-table :requisitions="$requisitions" :scope="$jobSite" :showLocation="false" :hasFilters="$this->hasFilters()" />
    </div>

    @include('livewire.requisition.partials.form-modal', [
        'contextName' => $jobSite->project?->project_name.' — '.$jobSite->job_site_name,
        'showJobSitePicker' => false,
        'jobSites' => collect(),
        'canAssign' => auth()->user()->can('requisitions.assign', $jobSite),
        'eligibleBuyers' => auth()->user()->can('requisitions.assign', $jobSite) ? $this->eligibleBuyers() : collect(),
    ])

    @include('livewire.requisition.partials.view-modal', [
        'canReview' => $viewingRequisition
            ? auth()->user()->can('requisitions.approve', $viewingRequisition)
            : auth()->user()->can('requisitions.approve', $jobSite),
        'selfApproval' => $viewingRequisition
            && $this->isSelfApproval($viewingRequisition)
            && ! auth()->user()->can('requisitions.approve_own', $viewingRequisition),
        'canAssign' => $viewingRequisition
            ? auth()->user()->can('requisitions.assign', $viewingRequisition)
            : auth()->user()->can('requisitions.assign', $jobSite),
        'eligibleBuyers' => $viewingRequisition
            ? $this->eligibleBuyers($viewingRequisition)
            : collect(),
        'quotationsRoute' => route('jobsites.quotations', $jobSite).'?requisition='.($viewingRequisition->id ?? ''),
    ])
</x-jobsite-layout>
