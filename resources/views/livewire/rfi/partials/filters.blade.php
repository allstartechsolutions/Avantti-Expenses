{{--
    Shared by both levels. $jobSites is passed only from the project page,
    where a Location filter makes sense; a job-site page is already one
    location. $canSeeImpact gates the impact filter — the checkbox is not
    rendered without the grant, and the query refuses it as well.
--}}
@php
    $jobSites = $jobSites ?? null;
    $selectClass = 'px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
@endphp

<div class="space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex-1 flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="relative max-w-md w-full sm:w-auto">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="rfiSearch"
                    placeholder="{{ __('collaboration.placeholder.search_number_subject_drawing') }}"
                    class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <select wire:model.live="rfiStatusFilter" class="{{ $selectClass }}">
                <option value="live">{{ __('collaboration.label.open_rfis') }}</option>
                <option value="all">{{ __('All Statuses') }}</option>
                @foreach(\App\Models\Rfi::statusOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            @if($jobSites !== null)
                <select wire:model.live="rfiLocationFilter" class="{{ $selectClass }}">
                    <option value="all">{{ __('All Locations') }}</option>
                    <option value="project">{{ __('Project (General)') }}</option>
                    @foreach($jobSites as $site)
                        <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                    @endforeach
                </select>
            @endif

            @if(count($disciplineOptions) > 0)
                <select wire:model.live="rfiDisciplineFilter" class="{{ $selectClass }}">
                    <option value="all">{{ __('collaboration.label.all_disciplines') }}</option>
                    @foreach($disciplineOptions as $discipline)
                        <option value="{{ $discipline }}">{{ \App\Models\Rfi::disciplineLabel($discipline) }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="rfiBallInCourtFilter" class="{{ $selectClass }}">
                <option value="all">{{ __('Anyone') }}</option>
                <option value="mine">{{ __('collaboration.label.waiting_me') }}</option>
                <option value="nobody">{{ __('collaboration.label.nobody') }}</option>
                @foreach($ballInCourtOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        @can('rfis.create', $scope)
            <x-ui.button variant="primary" icon="plus" :href="$createUrl">
                {{ __('collaboration.label.new_rfi') }}
            </x-ui.button>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-4 text-sm">
        <label class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-300 cursor-pointer">
            <input type="checkbox" wire:model.live="rfiOverdueOnly"
                class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189] dark:bg-slate-700">
            {{ __('collaboration.label.overdue_only') }}
        </label>

        @if($canSeeImpact)
            <label class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" wire:model.live="rfiImpactOnly"
                    class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189] dark:bg-slate-700">
                {{ __('collaboration.label.cost_schedule_impact') }}
            </label>
        @endif

        @if($this->hasRfiFilters())
            <button type="button" wire:click="clearRfiFilters"
                class="text-[#3F5189] dark:text-indigo-400 hover:underline font-medium">
                {{ __('Clear filters') }}
            </button>
        @endif
    </div>
</div>
