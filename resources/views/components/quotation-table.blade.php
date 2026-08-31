@props([
    'quotations',
    'showLocation' => true,
    'hasFilters' => false,
    /** The project or job site this list belongs to — what the grants are asked about. */
    'scope' => null,
])

@if($quotations->count() > 0)
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Quotation') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Type') }}</th>
                        @if($showLocation)
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Location') }}</th>
                        @endif
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Worked By') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Proposals') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Responses Due') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Items') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($quotations as $quotation)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $quotation->title }}</div>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $quotation->quotation_number ?? '#'.$quotation->id }}
                                    &middot; {{ $quotation->created_at->appDate() }}
                                    @if($quotation->requisition)
                                        &middot; {{ $quotation->requisition->requisition_number }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $quotation->type === 'service'
                                        ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300'
                                        : 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300' }}">
                                    {{ $quotation->getTypeLabel() }}
                                </span>
                            </td>
                            @if($showLocation)
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($quotation->jobSite)
                                        <span class="text-sm text-slate-900 dark:text-white">{{ $quotation->jobSite->job_site_name }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                            {{ __('Project (General)') }}
                                        </span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($quotation->assignedTo)
                                    <span class="text-slate-900 dark:text-white">{{ $quotation->assignedTo->name }}</span>
                                    @if($quotation->relationLoaded('assignees') && $quotation->assignees->isNotEmpty())
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ trans_choice('+:count more|+:count more', $quotation->assignees->count(), ['count' => $quotation->assignees->count()]) }}
                                        </div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                        {{ __('Unassigned') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="text-slate-900 dark:text-white font-medium">{{ $quotation->respondedCount() }}</span>
                                <span class="text-slate-500 dark:text-slate-400">/ {{ $quotation->invitedCount() }}</span>
                                @if($quotation->invitedCount() > 0 && ! $quotation->meetsProposalNorm())
                                    <div class="text-xs {{ $quotation->meetsProposalMinimum() ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400' }}">
                                        {{ $quotation->meetsProposalMinimum() ? __('Below the 3-proposal norm') : __('Not enough to award yet') }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($quotation->responses_due_at)
                                    <span class="{{ $quotation->responsesOverdue() ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-900 dark:text-white' }}">
                                        {{ $quotation->responses_due_at->appDate() }}
                                    </span>
                                    @if($quotation->responsesOverdue())
                                        <div class="text-xs text-red-600 dark:text-red-400">{{ __('Overdue') }}</div>
                                    @endif
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">{{ $quotation->items_count }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($quotation->status)
                                        @case('draft') bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 @break
                                        @case('sent') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300 @break
                                        @case('comparing') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300 @break
                                        @case('negotiating') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300 @break
                                        @case('awarded') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300 @break
                                        @case('converted') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300 @break
                                        @case('cancelled') bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400 @break
                                    @endswitch
                                ">
                                    {{ $quotation->getStatusLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    @if($quotation->respondedCount() > 0)
                                        <x-ui.icon-button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="openComparisonModal({{ $quotation->id }})"
                                            icon="chart"
                                            title="{{ __('Comparison Map') }}" />
                                    @endif
                                    <x-ui.view-edit-buttons
                                        :viewAction="'openViewModal('.$quotation->id.')'"
                                        :editAction="$quotation->canBeEdited() ? 'openEditModal('.$quotation->id.')' : null" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($quotations->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $quotations->links() }}
            </div>
        @endif
    </div>
@else
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No quotations') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if($hasFilters)
                    {{ __('No quotations match your filters.') }}
                @else
                    {{ __('A quotation asks several vendors to price the same scope, so the offers can be compared honestly.') }}
                @endif
            </p>
            @if(!$hasFilters)
                {{-- A round with no requisition behind it is its own grant (N1). --}}
                @can('quotations.create_standalone', $scope)
                    <div class="mt-6">
                        <x-ui.button variant="primary" icon="plus" wire:click="openAddModal">
                            {{ __('New Quotation') }}
                        </x-ui.button>
                    </div>
                @endcan
            @endif
        </div>
    </div>
@endif
