<x-project-layout :project="$project" active="change-orders" title="{{ __('Change Orders') }}">
    <div class="space-y-6">
        <!-- Search, filters and add -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 flex flex-col sm:flex-row gap-4">
                <div class="relative max-w-md">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="changeOrderSearch"
                        placeholder="{{ __('Search change orders...') }}"
                        class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>

                <select
                    wire:model.live="changeOrderLocationFilter"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="all">{{ __('All Locations') }}</option>
                    <option value="project">{{ __('Project (General)') }}</option>
                    @foreach($jobSites as $js)
                        <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                    @endforeach
                </select>

                <select
                    wire:model.live="changeOrderStatusFilter"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="all">{{ __('All Statuses') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="approved">{{ __('Approved') }}</option>
                    <option value="rejected">{{ __('Rejected') }}</option>
                </select>
            </div>

            <x-ui.button variant="primary" icon="plus" wire:click="openChangeOrderCreateModal">
                {{ __('Add Change Order') }}
            </x-ui.button>
        </div>

        @include('livewire.change-order.partials.summary-cards', ['summary' => $summary])

        @include('livewire.change-order.partials.list', [
            'changeOrders' => $changeOrders,
            'showLocationColumn' => true,
            'hasFilters' => $changeOrderSearch || $changeOrderStatusFilter !== 'all' || $changeOrderLocationFilter !== 'all',
        ])
    </div>

    @include('livewire.change-order.partials.form-modal', [
        'contextName' => $project->project_name,
        'showJobSitePicker' => true,
        'jobSites' => $jobSites,
        'coBudget' => $coBudget,
        'coLineSuggestions' => $coLineSuggestions,
        'changeOrderRecord' => $changeOrderRecord,
    ])
</x-project-layout>
