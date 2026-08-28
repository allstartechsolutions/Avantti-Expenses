@props([
    'requisitions',
    'showLocation' => true,
    'hasFilters' => false,
    /** The project or job site this list belongs to — what `requisitions.create` is asked about. */
    'scope' => null,
])

@if($requisitions->count() > 0)
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Requisition') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Type') }}</th>
                        @if($showLocation)
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Location') }}</th>
                        @endif
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Requested By') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Quoted By') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Needed By') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Items') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($requisitions as $requisition)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-2">
                                    @if($requisition->priority === 'urgent')
                                        <span class="mt-0.5 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300">
                                            {{ __('Urgent') }}
                                        </span>
                                    @endif
                                    <div>
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">
                                            {{ $requisition->title }}
                                        </div>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $requisition->requisition_number ?? '#'.$requisition->id }}
                                            &middot; {{ $requisition->created_at->format('M d, Y') }}
                                            @if($requisition->relationLoaded('quotations') && $requisition->isAlreadyQuoted())
                                                &middot; {{ trans_choice(':count round|:count rounds', $requisition->liveQuotations()->count(), ['count' => $requisition->liveQuotations()->count()]) }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $requisition->type === 'service'
                                        ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300'
                                        : 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300' }}">
                                    {{ $requisition->getTypeLabel() }}
                                </span>
                            </td>
                            @if($showLocation)
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($requisition->jobSite)
                                        <span class="text-sm text-slate-900 dark:text-white">{{ $requisition->jobSite->job_site_name }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                            {{ __('Project (General)') }}
                                        </span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                {{ $requisition->getRequesterName() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($requisition->assignedBuyer)
                                    <span class="text-slate-900 dark:text-white">{{ $requisition->assignedBuyer->name }}</span>
                                    {{-- Days waiting is the number that makes a stall visible
                                         before the reminder mail goes out. --}}
                                    @if($requisition->isAwaitingItsRound() && $requisition->assigned_at)
                                        <div class="text-xs {{ ($requisition->daysSinceAssigned() ?? 0) >= 7 ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-slate-500 dark:text-slate-400' }}">
                                            {{ trans_choice(':count day waiting|:count days waiting', $requisition->daysSinceAssigned() ?? 0, ['count' => $requisition->daysSinceAssigned() ?? 0]) }}
                                        </div>
                                    @endif
                                @elseif(in_array($requisition->status, ['approved', 'quoted'], true))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                        {{ __('Unassigned') }}
                                    </span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($requisition->needed_by)
                                    <span class="{{ $requisition->isOverdue() ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-900 dark:text-white' }}">
                                        {{ $requisition->needed_by->format('M d, Y') }}
                                    </span>
                                    @if($requisition->isOverdue())
                                        <div class="text-xs text-red-600 dark:text-red-400">{{ __('Overdue') }}</div>
                                    @endif
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                {{ $requisition->items_count }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($requisition->status)
                                        @case('draft') bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 @break
                                        @case('pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300 @break
                                        @case('approved') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300 @break
                                        @case('rejected') bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300 @break
                                        @case('quoted') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300 @break
                                        @case('fulfilled') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300 @break
                                        @case('cancelled') bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400 @break
                                    @endswitch
                                ">
                                    {{ $requisition->getStatusLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <x-ui.view-edit-buttons
                                    :viewAction="'openViewModal('.$requisition->id.')'"
                                    :editAction="$requisition->canBeEdited() && auth()->user()->can('requisitions.edit', $requisition)
                                        ? 'openEditModal('.$requisition->id.')'
                                        : null" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($requisitions->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $requisitions->links() }}
            </div>
        @endif
    </div>
@else
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No requisitions') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if($hasFilters)
                    {{ __('No requisitions match your filters.') }}
                @else
                    {{ __('A requisition is the site asking for what it needs. Approve it, then quote it.') }}
                @endif
            </p>
            @if(!$hasFilters)
                @can('requisitions.create', $scope)
                    <div class="mt-6">
                        <x-ui.button variant="primary" icon="plus" wire:click="openAddModal">
                            {{ __('Add Requisition') }}
                        </x-ui.button>
                    </div>
                @endcan
            @endif
        </div>
    </div>
@endif
