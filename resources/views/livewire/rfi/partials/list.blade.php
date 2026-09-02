{{--
    The RFI table, shared by both levels.

    $showLocationColumn is on for the project page, where a row could be the
    project's own or any site's, and off for a job-site page where it would
    repeat the same word down the screen.

    $canSeeImpact gates the impact marks. Whether a question carries a cost or
    a delay is not shown to somebody without `rfis.view_impact` — that is the
    grant that keeps it from an outside projetista, rather than a conditional
    on who they are.
--}}
@php
    $showLocationColumn = $showLocationColumn ?? false;
@endphp

<div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($rfis->isEmpty())
        <div class="p-10 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>

            @if($this->hasRfiFilters())
                <p class="mt-3 font-medium text-slate-900 dark:text-white">{{ __('collaboration.message.rfis_match_these_filters') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('collaboration.help.there_may_others_here_filters') }}</p>
                <div class="mt-4">
                    <x-ui.button variant="secondary" size="sm" wire:click="clearRfiFilters">{{ __('Clear filters') }}</x-ui.button>
                </div>
            @else
                <p class="mt-3 font-medium text-slate-900 dark:text-white">{{ __('collaboration.message.rfis') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.rfi_formal_question_put_designer') }}
                </p>
                @can('rfis.create', $scope)
                    <div class="mt-4">
                        <x-ui.button variant="primary" size="sm" icon="plus" :href="$createUrl">{{ __('collaboration.label.raise_first_rfi') }}</x-ui.button>
                    </div>
                @endcan
            @endif
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Number') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Subject') }}</th>
                        @if($showLocationColumn)
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Location') }}</th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('collaboration.label.ball_court') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('collaboration.label.due') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($rfis as $rfi)
                        @php
                            $statusTone = match ($rfi->status) {
                                \App\Models\Rfi::DRAFT => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                                \App\Models\Rfi::OPEN => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                                \App\Models\Rfi::ANSWERED => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
                                \App\Models\Rfi::CLOSED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                            };
                        @endphp

                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-sm font-medium text-slate-900 dark:text-white">{{ $rfi->number }}</span>
                                @if($rfi->priority === 'urgent' || $rfi->priority === 'high')
                                    <span class="ml-2 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $rfi->getPriorityLabel() }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <a href="{{ route('rfis.show', $rfi) }}" class="text-sm font-medium text-[#3F5189] dark:text-indigo-400 hover:underline">{{ $rfi->subject }}</a>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    @if($rfi->discipline){{ $rfi->getDisciplineLabel() }}@endif
                                    @if($rfi->drawing_ref) · {{ $rfi->drawing_ref }}@endif
                                    @if($rfi->spec_section) · {{ $rfi->spec_section }}@endif
                                </p>

                                @if($canSeeImpact && ($rfi->cost_impact || $rfi->schedule_impact))
                                    <p class="mt-1 flex flex-wrap gap-1">
                                        @if($rfi->cost_impact)
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[11px] font-medium bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200">
                                                @if($rfi->cost_impact_amount !== null)
                                                    <x-ui.money :amount="$rfi->cost_impact_amount" :scope="$scope" rollup />
                                                @else
                                                    {{ __('collaboration.label.cost_impact') }}
                                                @endif
                                            </span>
                                        @endif
                                        @if($rfi->schedule_impact)
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                                {{ $rfi->schedule_impact_days
                                                    ? trans_choice(':count day|:count days', $rfi->schedule_impact_days, ['count' => $rfi->schedule_impact_days])
                                                    : __('collaboration.label.schedule_impact') }}
                                            </span>
                                        @endif
                                    </p>
                                @endif
                            </td>

                            @if($showLocationColumn)
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">
                                    {{ $rfi->jobSite?->job_site_name ?? __('Project (General)') }}
                                </td>
                            @endif

                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusTone }}">
                                    {{ $rfi->getStatusLabel() }}
                                </span>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">
                                {{ $rfi->ballInCourt?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($rfi->due_date)
                                    <span class="{{ $rfi->isOverdue() ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $rfi->due_date->appDate() }}
                                    </span>
                                    @if($rfi->isOverdue())
                                        <span class="block text-xs text-rose-600 dark:text-rose-400">
                                            {{ trans_choice(':count day late|:count days late', $rfi->daysOverdue(), ['count' => $rfi->daysOverdue()]) }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td>


                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <x-ui.view-edit-buttons :viewRoute="route('rfis.show', $rfi)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($rfis->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                {{ $rfis->links() }}
            </div>
        @endif
    @endif
</div>
