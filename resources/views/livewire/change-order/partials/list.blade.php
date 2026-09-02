{{--
    Change order table, shared by the project and job-site levels.
    Expects: $changeOrders, $showLocationColumn, $hasFilters, $scope
--}}
@php
    $money = fn ($value) => Number::currency((float) $value, config('app.currency'), config('app.locale'));
    $signed = fn ($value) => ((float) $value >= 0 ? '+' : '') . Number::currency((float) $value, config('app.currency'), config('app.locale'));
    $statusStyles = [
        'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    ];
@endphp

@if($changeOrders->count() > 0)
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Change Order') }}</th>
                        @if($showLocationColumn)
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Location') }}</th>
                        @endif
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Billed') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Cost') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Margin') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($changeOrders as $changeOrder)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors" wire:key="co-row-{{ $changeOrder->id }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($changeOrder->co_number)
                                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $changeOrder->co_number }}</span>
                                    @endif
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $changeOrder->title }}</span>
                                    @if($changeOrder->file_path)
                                        <a href="{{ route('files.download', ['path' => $changeOrder->file_path]) }}" title="{{ __('Download File') }}" class="text-[#3F5189] dark:text-[#4A5A96]">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $changeOrder->requested_date->translatedFormat('d M Y') }}
                                    @if($changeOrder->items->isNotEmpty())
                                        · {{ trans_choice(':count cost code|:count cost codes', $changeOrder->items->count(), ['count' => $changeOrder->items->count()]) }}
                                    @endif
                                </div>
                            </td>
                            @if($showLocationColumn)
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($changeOrder->jobSite)
                                        <span class="text-sm text-slate-900 dark:text-white">{{ $changeOrder->jobSite->job_site_name }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                            {{ __('Project (General)') }}
                                        </span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusStyles[$changeOrder->status] ?? $statusStyles['draft'] }}">
                                    {{ $changeOrder->getStatusLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium {{ $changeOrder->amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                                {{ $signed($changeOrder->amount) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                @if($changeOrder->items->isEmpty())
                                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('Not costed') }}</span>
                                @else
                                    <span class="{{ $changeOrder->cost_impact < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ $signed($changeOrder->cost_impact) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                @if($changeOrder->items->isEmpty())
                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                @else
                                    <span class="font-medium {{ $changeOrder->margin < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ $money($changeOrder->margin) }}
                                    </span>
                                    @if($changeOrder->margin_percent !== null)
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ number_format($changeOrder->margin_percent, 1) }}%</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    @if(! $changeOrder->isApproved() && auth()->user()->can('change-orders.approve', $changeOrder))
                                        <button wire:click="approveChangeOrder({{ $changeOrder->id }})" title="{{ __('Approve') }}" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </button>
                                    @endif
                                    <button wire:click="openChangeOrderViewModal({{ $changeOrder->id }})" title="{{ __('View') }}" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                    @can('change-orders.edit', $changeOrder)
                                    <button wire:click="openChangeOrderEditModal({{ $changeOrder->id }})" title="{{ __('Edit') }}" class="text-[#3F5189] hover:text-[#4A5A96] dark:text-[#4A5A96] dark:hover:text-[#5A6AA6]">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    @endcan
                                    {{-- An approved change order is revising the budget; deleting it
                                         would take its lines back out with no record. Undo the
                                         approval first. --}}
                                    @if(! $changeOrder->isApproved())
                                        @can('change-orders.delete', $changeOrder)
                                        <button wire:click="deleteChangeOrder({{ $changeOrder->id }})" wire:confirm="{{ __('Are you sure you want to delete this change order?') }}" title="{{ __('Delete') }}" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            @if($hasFilters)
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No change orders match these filters') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Clear the search or the status to see the rest.') }}</p>
            @else
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No change orders') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('A change order records what the client is billed for a change, and what it costs across the cost codes.') }}</p>
                @can('change-orders.create', $scope)
                    <div class="mt-6">
                        <x-ui.button variant="primary" icon="plus" wire:click="openChangeOrderCreateModal">
                            {{ __('Add Change Order') }}
                        </x-ui.button>
                    </div>
                @endcan
            @endif
        </div>
    </div>
@endif
