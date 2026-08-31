<div>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $paymentBatch->name }}</h1>
                @php
                    $headerColor = match($paymentBatch->getStatusColor()) {
                        'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-700 dark:text-amber-300',
                        'green' => 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-300',
                        'red' => 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-300',
                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $headerColor }}">
                    {{ __($paymentBatch->getStatusLabel()) }}
                </span>
            </div>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Payment batch details') }}</p>
        </div>
        <div class="flex items-center gap-3 mt-4 md:mt-0">
            <x-ui.button variant="secondary" href="{{ route('payment-batches.index') }}" icon="arrow-left">
                {{ __('Back') }}
            </x-ui.button>
            @if($paymentBatch->canBeEdited())
                <x-ui.button variant="primary" href="{{ route('payment-batches.edit', $paymentBatch->id) }}" icon="edit">
                    {{ __('Edit Batch') }}
                </x-ui.button>
            @endif
        </div>
    </div>

    <!-- Batch Details Card -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Batch Details') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Payment Date') }}</p>
                <p class="text-sm text-slate-900 dark:text-white mt-1">{{ $paymentBatch->payment_date->appDate() }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Created By') }}</p>
                <p class="text-sm text-slate-900 dark:text-white mt-1">{{ $paymentBatch->createdBy?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Created At') }}</p>
                <p class="text-sm text-slate-900 dark:text-white mt-1">{{ $paymentBatch->created_at->appDateTime() }}</p>
            </div>
            @if($paymentBatch->approved_by)
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Approved By') }}</p>
                    <p class="text-sm text-slate-900 dark:text-white mt-1">{{ $paymentBatch->approvedBy?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Approved At') }}</p>
                    <p class="text-sm text-slate-900 dark:text-white mt-1">{{ $paymentBatch->approved_at?->appDateTime() ?? '—' }}</p>
                </div>
            @endif
            @if($paymentBatch->notes)
                <div class="md:col-span-3">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Notes') }}</p>
                    <p class="text-sm text-slate-900 dark:text-white mt-1">{{ $paymentBatch->notes }}</p>
                </div>
            @endif
        </div>

        @if($paymentBatch->client_id || $paymentBatch->project_id || $paymentBatch->subcontractor_id || $paymentBatch->project_manager_id || $paymentBatch->contract_status_filter)
            <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ __('Contract Filters') }}</h4>
                <div class="flex flex-wrap gap-2">
                    @if($paymentBatch->client)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ __('Client') }}: {{ $paymentBatch->client->company_name }}
                        </span>
                    @endif
                    @if($paymentBatch->project)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ __('Project') }}: {{ $paymentBatch->project->project_name }}
                        </span>
                    @endif
                    @if($paymentBatch->subcontractor)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ __('Subcontractor') }}: {{ $paymentBatch->subcontractor->company_name }}
                        </span>
                    @endif
                    @if($paymentBatch->projectManager)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            PM: {{ $paymentBatch->projectManager->name }}
                        </span>
                    @endif
                    @if($paymentBatch->contract_status_filter)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ __('Status') }}: {{ __(ucwords(str_replace('_', ' ', $paymentBatch->contract_status_filter))) }}
                        </span>
                    @endif
                    @if($paymentBatch->show_zero_balance)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ __('Including Paid/Cancelled') }}
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Amount -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Total Amount') }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ Number::currency($this->summary['total_amount'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Approved Amount -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Approved') }}</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ Number::currency($this->summary['approved_amount'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Pending Amount -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Pending') }}</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {{ Number::currency($this->summary['pending_amount'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Rejected Amount -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Rejected') }}</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                        {{ Number::currency($this->summary['rejected_amount'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Batch Items') }} ({{ $items->count() }})</h3>
        </div>

        @if($items->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Subcontractor') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Project') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Job Site') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Contract #') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Amount') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Method') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Phase') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Notes') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($items as $item)
                            @php
                                $isRejected = $item->status === 'rejected';
                                $isApproved = $item->status === 'approved';
                                $itemColor = match($item->getStatusColor()) {
                                    'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-700 dark:text-amber-300',
                                    'green' => 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-300',
                                    'red' => 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-300',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                };
                            @endphp
                            <tr class="{{ $isRejected ? 'bg-red-50/50 dark:bg-red-900/10' : ($isApproved ? 'bg-green-50/50 dark:bg-green-900/10' : '') }} hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white {{ $isRejected ? 'line-through' : '' }}">
                                        {{ $item->contract->subcontractor?->company_name ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white {{ $isRejected ? 'line-through' : '' }}">
                                        {{ $item->contract->project->project_name }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $item->contract->project->client?->company_name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="text-sm text-slate-900 dark:text-white {{ $isRejected ? 'line-through' : '' }}">
                                        {{ $item->contract->jobSite?->job_site_name ?? __('Project General') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('contracts.show', $item->contract_id) }}"
                                       class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                        {{ $item->contract->contract_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <span class="text-sm font-medium text-slate-900 dark:text-white {{ $isRejected ? 'line-through' : '' }}">
                                        {{ Number::currency($item->amount, config('app.currency'), config('app.locale')) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="text-sm text-slate-600 dark:text-slate-400 {{ $isRejected ? 'line-through' : '' }}">
                                        {{ __($item->getPaymentMethodLabel()) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="text-sm text-slate-600 dark:text-slate-400 {{ $isRejected ? 'line-through' : '' }}">
                                        {{ $item->phase ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-slate-600 dark:text-slate-400 {{ $isRejected ? 'line-through' : '' }} max-w-xs truncate block" title="{{ $item->notes }}">
                                        {{ $item->notes ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $itemColor }}">
                                        {{ __($item->getStatusLabel()) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No items in this batch') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __("This batch doesn't have any items yet.") }}
                </p>
                @if($paymentBatch->canBeEdited())
                    <div class="mt-6">
                        <x-ui.button variant="primary" href="{{ route('payment-batches.edit', $paymentBatch->id) }}" icon="edit">
                            {{ __('Edit Batch') }}
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
