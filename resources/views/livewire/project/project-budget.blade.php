<x-project-layout :project="$project" active="budget" title="{{ __('Budget') }}">
    @php
        $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
        $signedAmount = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));
    @endphp

    <div class="space-y-6">
        @if($rollup)
            <!-- Every budget on this project, added up -->
            <div class="bg-gradient-to-r from-[#3F5189] to-[#5A6FA8] rounded-lg p-6 text-white">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <p class="text-sm text-white/80">{{ __('Revised Budget') }} &mdash; {{ __('whole project') }}</p>
                        <p class="text-3xl font-bold mt-1">{{ $fmt($rollup['revised']) }}</p>
                        <p class="text-sm text-white/70 mt-1">
                            {{ __('Original') }} {{ $fmt($rollup['original']) }}
                            @if((float) $rollup['changes'] != 0.0)
                                &bull; {{ __('Approved changes') }} {{ $signedAmount($rollup['changes']) }}
                            @endif
                        </p>
                    </div>
                    <div class="lg:text-right">
                        <p class="text-sm text-white/80">{{ (float) $rollup['remaining'] < 0 ? __('Over budget') : __('Remaining') }}</p>
                        <p class="text-3xl font-bold mt-1 {{ (float) $rollup['remaining'] < 0 ? 'text-red-200' : '' }}">{{ $fmt(abs($rollup['remaining'])) }}</p>
                        @if($rollup['percent_committed'] !== null)
                            <div class="mt-2 flex items-center gap-2 lg:justify-end">
                                <div class="relative w-40 h-2 bg-white/20 rounded-full overflow-hidden">
                                    <div class="absolute inset-y-0 left-0 bg-white/50" style="width: {{ min(100, max(0, $rollup['percent_committed'])) }}%"></div>
                                    <div class="absolute inset-y-0 left-0 bg-white" style="width: {{ min(100, max(0, $rollup['percent_spent'] ?? 0)) }}%"></div>
                                </div>
                                <span class="text-sm text-white/80">{{ number_format($rollup['percent_committed'], 0) }}% {{ __('used') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="mt-6 pt-4 border-t border-white/20 grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-white/70">{{ __('Committed') }}</p>
                        <p class="font-semibold mt-0.5">{{ $fmt($rollup['committed']) }}</p>
                    </div>
                    <div>
                        <p class="text-white/70">{{ __('Actual') }}</p>
                        <p class="font-semibold mt-0.5">{{ $fmt($rollup['actual']) }}</p>
                    </div>
                    <div>
                        <p class="text-white/70">{{ __('Projected') }}</p>
                        <p class="font-semibold mt-0.5">{{ $fmt($rollup['projected']) }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Project Budget Section -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Project Budget') }}</h3>
                @if(!$projectBudget)
                    <x-ui.button
                        variant="primary"
                        size="sm"
                        href="{{ route('projects.budgets.create', $project->id) }}"
                        icon="plus">
                        {{ __('Create Budget') }}
                    </x-ui.button>
                @endif
            </div>

            <div class="p-6">
                @if($projectBudget)
                    <div class="flex items-center justify-between p-4 bg-gradient-to-r from-[#3F5189]/10 to-[#5A6FA8]/10 dark:from-[#3F5189]/20 dark:to-[#5A6FA8]/20 rounded-lg">
                        <div>
                            <h4 class="font-semibold text-slate-900 dark:text-white">{{ $projectBudget->name }}</h4>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                {{ $projectBudget->items_count }} cost codes
                                @if($projectBudget->sourceTemplate)
                                    &bull; Template: {{ $projectBudget->sourceTemplate->name }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            @include('livewire.budget.partials.budget-figures', [
                                'totals' => $budgetTotals[$projectBudget->id] ?? null,
                            ])
                            <div class="flex items-center justify-end gap-2 mt-2">
                                <x-ui.button
                                    variant="secondary"
                                    size="sm"
                                    href="{{ route('budgets.show', $projectBudget->id) }}"
                                    icon="eye">
                                    {{ __('View') }}
                                </x-ui.button>
                                <x-ui.button
                                    variant="ghost"
                                    size="sm"
                                    href="{{ route('budgets.edit', $projectBudget->id) }}"
                                    icon="edit">
                                    {{ __('Edit') }}
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <h4 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No project budget') }}</h4>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Create a budget to track cost allocation for this project.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Job Site Budgets Section -->
        @if($jobSites->count() > 0)
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Job Site Budgets') }}</h3>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($jobSites as $jobSite)
                        @php
                            $jobSiteBudget = $jobSiteBudgets->firstWhere('job_site_id', $jobSite->id);
                        @endphp
                        <div class="p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-slate-100 dark:bg-slate-700 rounded-lg">
                                    <svg class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <a href="{{ route('jobsites.overview', $jobSite->id) }}" class="font-medium text-slate-900 dark:text-white hover:text-[#3F5189]">
                                        {{ $jobSite->job_site_name }}
                                    </a>
                                    @if($jobSiteBudget)
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ $jobSiteBudget->name }} &bull; {{ $jobSiteBudget->items_count }} cost codes
                                        </p>
                                    @else
                                        <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('No budget') }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                @if($jobSiteBudget)
                                    @include('livewire.budget.partials.budget-figures', [
                                        'totals' => $budgetTotals[$jobSiteBudget->id] ?? null,
                                    ])
                                    <x-ui.button
                                        variant="ghost"
                                        size="sm"
                                        href="{{ route('budgets.show', $jobSiteBudget->id) }}"
                                        icon="eye">
                                    </x-ui.button>
                                @else
                                    <x-ui.button
                                        variant="secondary"
                                        size="sm"
                                        href="{{ route('job-sites.budgets.create', $jobSite->id) }}"
                                        icon="plus">
                                        {{ __('Create') }}
                                    </x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-project-layout>
