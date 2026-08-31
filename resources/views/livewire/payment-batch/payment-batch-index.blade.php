<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Payment Batches') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Plan and approve contract payments in batches') }}</p>
            </div>
            <div>
                <x-ui.button
                    variant="primary"
                    href="{{ route('payment-batches.create') }}"
                    icon="plus">
                    {{ __('New Batch') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Status Filter Tabs -->
    <div class="mb-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="{{ __('Tabs') }}">
                <button wire:click="setStatusFilter('')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === '' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('All') }} <span class="ml-1 text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full">{{ $statusCounts['all'] }}</span>
                </button>
                <button wire:click="setStatusFilter('draft')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'draft' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Draft') }} <span class="ml-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full">{{ $statusCounts['draft'] }}</span>
                </button>
                <button wire:click="setStatusFilter('partially_approved')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'partially_approved' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Partially Approved') }} <span class="ml-1 text-xs bg-amber-100 dark:bg-amber-700 text-amber-600 dark:text-amber-300 px-2 py-0.5 rounded-full">{{ $statusCounts['partially_approved'] }}</span>
                </button>
                <button wire:click="setStatusFilter('approved')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'approved' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Approved') }} <span class="ml-1 text-xs bg-green-100 dark:bg-green-700 text-green-600 dark:text-green-300 px-2 py-0.5 rounded-full">{{ $statusCounts['approved'] }}</span>
                </button>
                <button wire:click="setStatusFilter('cancelled')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'cancelled' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Cancelled') }} <span class="ml-1 text-xs bg-red-100 dark:bg-red-700 text-red-600 dark:text-red-300 px-2 py-0.5 rounded-full">{{ $statusCounts['cancelled'] }}</span>
                </button>
            </nav>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex-1 max-w-md">
                    <label for="search" class="sr-only">{{ __('Search batches') }}</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            class="block w-full pl-10 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md leading-5 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]"
                            placeholder="{{ __('Search by batch name...') }}"
                        >
                    </div>
                </div>

                @if($search)
                    <x-ui.button
                        variant="secondary"
                        wire:click="$set('search', '')"
                        icon="x">
                        {{ __('Clear Search') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <!-- Batches Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($batches->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Name') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Payment Date') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Items') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Total Amount') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Created By') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Created At') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($batches as $batch)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $batch->name }}
                                    </div>
                                    @if($batch->client || $batch->project || $batch->subcontractor || $batch->projectManager || $batch->contract_status_filter)
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @if($batch->client)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                                    {{ $batch->client->company_name }}
                                                </span>
                                            @endif
                                            @if($batch->project)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                                    {{ $batch->project->project_name }}
                                                </span>
                                            @endif
                                            @if($batch->subcontractor)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                                                    {{ $batch->subcontractor->company_name }}
                                                </span>
                                            @endif
                                            @if($batch->projectManager)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                                    PM: {{ $batch->projectManager->name }}
                                                </span>
                                            @endif
                                            @if($batch->contract_status_filter)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                                                    {{ __(ucwords(str_replace('_', ' ', $batch->contract_status_filter))) }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $batch->payment_date->appDate() }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $batch->items_count }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                        ${{ number_format(($batch->items_sum_amount ?? 0) / 100, 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $colorClasses = match($batch->getStatusColor()) {
                                            'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-700 dark:text-amber-300',
                                            'green' => 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-300',
                                            'red' => 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-300',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClasses }}">
                                        {{ __($batch->getStatusLabel()) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $batch->createdBy?->name ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $batch->created_at->appDate() }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.view-edit-buttons
                                            :viewRoute="route('payment-batches.show', $batch->id)"
                                            :editRoute="$batch->canBeEdited() ? route('payment-batches.edit', $batch->id) : null" />
                                        @if($batch->isDraft())
                                            <x-ui.icon-button
                                                variant="danger"
                                                size="sm"
                                                wire:click="deleteBatch({{ $batch->id }})"
                                                wire:confirm="{{ __('Are you sure you want to delete this batch?') }}"
                                                icon="trash"
                                                title="{{ __('Delete') }}" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $batches->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">
                    @if($search || $statusFilter)
                        {{ __('No payment batches found') }}
                    @else
                        {{ __('No payment batches yet') }}
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if($search || $statusFilter)
                        {{ __('Try adjusting your search or filter.') }}
                    @else
                        {{ __('Get started by creating a new payment batch.') }}
                    @endif
                </p>
                @if(!$search && !$statusFilter)
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            href="{{ route('payment-batches.create') }}"
                            icon="plus">
                            {{ __('New Batch') }}
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
