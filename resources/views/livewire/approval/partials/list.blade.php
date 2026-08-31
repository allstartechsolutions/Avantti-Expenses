{{--
    The approvals table.

    The column that earns its place here and has no equivalent on the RFI list
    is Revision: an approval on revision 3 is a different conversation from one
    on revision 0, and it is the first thing somebody scans for.
--}}
@php
    $showLocationColumn = $showLocationColumn ?? false;
@endphp

<div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
    @if($approvals->isEmpty())
        <div class="p-10 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>

            @if($this->hasApprovalFilters())
                <p class="mt-3 font-medium text-slate-900 dark:text-white">{{ __('collaboration.message.approvals_match_these_filters') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('collaboration.help.there_may_others_here_filters') }}</p>
                <div class="mt-4">
                    <x-ui.button variant="secondary" size="sm" wire:click="clearApprovalFilters">{{ __('Clear filters') }}</x-ui.button>
                </div>
            @else
                <p class="mt-3 font-medium text-slate-900 dark:text-white">{{ __('collaboration.message.approvals') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.approval_material_sample_shop_drawing') }}
                </p>
                @can('approvals.create', $scope)
                    <div class="mt-4">
                        <x-ui.button variant="primary" size="sm" icon="plus" :href="$createUrl">{{ __('collaboration.label.raise_first_approval') }}</x-ui.button>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Title') }}</th>
                        @if($showLocationColumn)
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Location') }}</th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('collaboration.message.rev') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('collaboration.label.with') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('collaboration.label.due') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($approvals as $approval)
                        @php
                            $statusTone = match ($approval->status) {
                                \App\Models\Approval::DRAFT => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                                \App\Models\Approval::IN_REVIEW => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
                                \App\Models\Approval::APPROVED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                \App\Models\Approval::REJECTED => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                            };
                        @endphp

                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-sm font-medium text-slate-900 dark:text-white">{{ $approval->number }}</span>
                            </td>

                            <td class="px-4 py-3">
                                <a href="{{ route('approvals.show', $approval) }}" class="text-sm font-medium text-[#3F5189] dark:text-indigo-400 hover:underline">
                                    {{ $approval->title }}
                                </a>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $approval->getTypeLabel() }}</p>

                                @if($approval->certificateNeedsAttention())
                                    <p class="mt-1">
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[11px] font-medium
                                            {{ $approval->certificate->hasExpired()
                                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                            {{ $approval->certificate->hasExpired()
                                                ? __('collaboration.label.certificate_expired')
                                                : __('collaboration.label.certificate_expires', ['date' => $approval->certificate->valid_until->appDate()]) }}
                                        </span>
                                    </p>
                                @endif
                            </td>

                            @if($showLocationColumn)
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">
                                    {{ $approval->jobSite?->job_site_name ?? __('Project (General)') }}
                                </td>
                            @endif

                            <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-slate-600 dark:text-slate-300">
                                {{ $approval->current_revision }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusTone }}">
                                    {{ $approval->getStatusLabel() }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                @php
                                    $waiting = $approval->currentRevisionRecord?->currentReviewers() ?? collect();
                                @endphp

                                @if($waiting->isNotEmpty())
                                    {{ $waiting->map(fn ($r) => $r->user?->name)->filter()->join(', ') }}
                                @else
                                    {{ $approval->ballInCourt?->name ?? '—' }}
                                @endif
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($approval->due_date)
                                    <span class="{{ $approval->isOverdue() ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $approval->due_date->appDate() }}
                                    </span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td>


                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <x-ui.view-edit-buttons :viewRoute="route('approvals.show', $approval)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($approvals->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                {{ $approvals->links() }}
            </div>
        @endif
    @endif
</div>
