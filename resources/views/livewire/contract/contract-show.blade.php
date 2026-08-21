<div>
    {{-- Breadcrumbs --}}
    @php
        $breadcrumbs = [
            ['label' => __('Projects'), 'url' => route('projects.index')],
            ['label' => $contract->project->project_name, 'url' => route('projects.overview', $contract->project)],
        ];

        if ($contract->jobSite) {
            $breadcrumbs[] = ['label' => __('Job Sites'), 'url' => route('projects.jobsites', $contract->project)];
            $breadcrumbs[] = ['label' => $contract->jobSite->job_site_name, 'url' => route('jobsites.overview', $contract->jobSite)];
            $breadcrumbs[] = ['label' => __('Contracts'), 'url' => route('jobsites.contracts', $contract->jobSite)];
        } else {
            $breadcrumbs[] = ['label' => __('Contracts'), 'url' => route('projects.contracts', $contract->project)];
        }

        $breadcrumbs[] = ['label' => __('Contract') . ' ' . $contract->contract_number];
    @endphp
    <x-ui.breadcrumb :items="$breadcrumbs" />

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ __('Contract') }} {{ $contract->contract_number }}
                    </h1>
                    @php
                        $statusColors = [
                            'draft' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
                            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                            'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
                            'partially_paid' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                            'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400',
                            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                        ];
                        $statusLabels = [
                            'draft' => __('Draft'),
                            'active' => __('Active'),
                            'completed' => __('Completed'),
                            'partially_paid' => __('Partially Paid'),
                            'paid' => __('Paid'),
                            'cancelled' => __('Cancelled'),
                        ];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$contract->status] ?? '' }}">
                        {{ $statusLabels[$contract->status] ?? ucfirst($contract->status) }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $contract->project->project_name }}
                    @if($contract->jobSite)
                        / {{ $contract->jobSite->job_site_name }}
                    @else
                        / Project Level
                    @endif
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ $contract->job_site_id ? route('jobsites.contracts', $contract->job_site_id) : route('projects.contracts', $contract->project_id) }}"
                    icon="arrow-left">
                    {{ __('Back to List') }}
                </x-ui.button>
                @can('contracts.edit', $contract)
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('contracts.edit', $contract->id) }}"
                        icon="edit">
                        {{ __('Edit') }}
                    </x-ui.button>
                @endcan
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Contract Details Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contract Details') }}</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Contract #') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->contract_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Subcontractor') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->subcontractor?->company_name ?? __('Not specified') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Contact') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                                {{ $contract->subcontractorEmployee?->name ?? __('Not specified') }}@if($contract->subcontractorEmployee?->title) ({{ $contract->subcontractorEmployee->title }})@endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Location') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                                @if($contract->isProjectLevel())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400">
                                        {{ __('Project (General)') }}
                                    </span>
                                @else
                                    {{ $contract->jobSite->job_site_name }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Created By') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->createdBy?->name ?? __('Unknown') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Start Date') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->start_date->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('End Date') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->end_date?->format('M d, Y') ?? __('Not set') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Financial Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Financial') }}</h3>
                </div>
                <div class="p-6">
                    @php
                        $changeOrdersPositive = $contract->changeOrders->filter(fn($co) => $co->getRawOriginal('amount') > 0)->sum(fn($co) => $co->getRawOriginal('amount')) / 100;
                        $changeOrdersNegative = $contract->changeOrders->filter(fn($co) => $co->getRawOriginal('amount') < 0)->sum(fn($co) => $co->getRawOriginal('amount')) / 100;
                        $hasChangeOrders = $changeOrdersPositive != 0 || $changeOrdersNegative != 0;
                        $adjustedAmount = $contract->getAdjustedAmount();
                        $balanceDue = $contract->getBalanceDue();
                    @endphp
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Original Amount') }}</dt>
                            <dd class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                                {{ Number::currency($contract->amount, config('app.currency'), config('app.locale')) }}
                            </dd>
                        </div>
                        @if($changeOrdersPositive != 0)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Change Orders') }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">
                                    +{{ Number::currency($changeOrdersPositive, config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                        @endif
                        @if($changeOrdersNegative != 0)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Deductions') }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-red-600 dark:text-red-400">
                                    {{ Number::currency($changeOrdersNegative, config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                        @endif
                        @if($hasChangeOrders)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Adjusted Amount') }}</dt>
                                <dd class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                                    {{ Number::currency($adjustedAmount, config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                        @endif
                        @if($contract->payments->count() > 0)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Amount Paid') }}</dt>
                                <dd class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">
                                    {{ Number::currency($contract->getAmountPaid(), config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Balance Due') }}</dt>
                                <dd class="mt-1 text-xl font-semibold {{ $balanceDue > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ Number::currency($balanceDue, config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                        @endif
                        @if($contract->hasRetention())
                            <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    {{ __('Retention') }} ({{ rtrim(rtrim(number_format((float) $contract->retention_percent, 2, '.', ''), '0'), '.') }}%)
                                </dt>
                                <dd class="mt-2 grid grid-cols-3 gap-3 text-sm">
                                    <div>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Held') }}</span>
                                        <span class="font-semibold text-slate-900 dark:text-white">
                                            {{ Number::currency($contract->getRetentionHeld(), config('app.currency'), config('app.locale')) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Released') }}</span>
                                        <span class="font-semibold text-green-600 dark:text-green-400">
                                            {{ Number::currency($contract->getRetentionReleased(), config('app.currency'), config('app.locale')) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('To Release') }}</span>
                                        <span class="font-semibold text-orange-600 dark:text-orange-400">
                                            {{ Number::currency($contract->getRetentionOutstanding(), config('app.currency'), config('app.locale')) }}
                                        </span>
                                    </div>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Schedule of Values (Cost Codes) -->
            @if($costCodeSchedule)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Cost Codes') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Contract amount, payments and progress per cost code') }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Cost Code') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Scheduled') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('% Complete') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Balance') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($costCodeSchedule as $row)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-6 py-3 text-sm {{ $row['budget_item_id'] === null ? 'italic text-slate-500 dark:text-slate-400' : 'text-slate-900 dark:text-white' }}">
                                            {{ $row['code_display'] }}
                                        </td>
                                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                                            {{ Number::currency($row['scheduled'], config('app.currency'), config('app.locale')) }}
                                        </td>
                                        <td class="px-6 py-3 text-sm text-right {{ $row['paid'] > 0 ? 'text-green-600 dark:text-green-400 font-medium' : 'text-slate-500 dark:text-slate-400' }}">
                                            {{ Number::currency($row['paid'], config('app.currency'), config('app.locale')) }}
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            @if($row['percent_complete'] !== null)
                                                <div class="flex items-center justify-end gap-2">
                                                    <div class="w-16 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                                        <div class="h-full bg-[#3F5189] dark:bg-[#4A5A96] rounded-full" style="width: {{ min(100, max(0, $row['percent_complete'])) }}%"></div>
                                                    </div>
                                                    <span class="text-sm text-slate-900 dark:text-white">{{ rtrim(rtrim(number_format($row['percent_complete'], 2, '.', ''), '0'), '.') }}%</span>
                                                </div>
                                            @else
                                                <span class="text-sm text-slate-400 dark:text-slate-500">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-sm text-right font-medium {{ $row['balance'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">
                                            {{ Number::currency($row['balance'], config('app.currency'), config('app.locale')) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-50 dark:bg-slate-900/50 border-t-2 border-slate-300 dark:border-slate-600">
                                <tr>
                                    <td class="px-6 py-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('Total') }}</td>
                                    <td class="px-6 py-3 text-sm text-right font-semibold text-slate-900 dark:text-white">
                                        {{ Number::currency($costCodeSchedule->sum('scheduled'), config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right font-semibold text-green-600 dark:text-green-400">
                                        {{ Number::currency($costCodeSchedule->sum('paid'), config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-6 py-3"></td>
                                    <td class="px-6 py-3 text-sm text-right font-semibold text-slate-900 dark:text-white">
                                        {{ Number::currency($costCodeSchedule->sum('balance'), config('app.currency'), config('app.locale')) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Payment Schedule (Cronograma) -->
            <livewire:contract.contract-schedule :contract="$contract" />

            <!-- Measurements (Medições) -->
            <livewire:contract.contract-measurements :contract="$contract" />

            <!-- Change Orders -->
            <livewire:contract.contract-change-orders :contract="$contract" />

            <!-- Notes Card -->
            @if($contract->notes)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Notes') }}</h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-slate-900 dark:text-white whitespace-pre-wrap">{{ $contract->notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Contract File Card -->
            @if($contract->contract_file_path)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contract File') }}</h3>
                    </div>
                    <div class="p-6">
                        <a href="{{ route('files.show', ['path' => $contract->contract_file_path]) }}" target="_blank" class="inline-flex items-center text-sm text-[#3F5189] hover:text-[#2F3F6F]">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            {{ __('View Document') }}
                        </a>
                        <span class="mx-2 text-slate-300">|</span>
                        <a href="{{ route('files.download', ['path' => $contract->contract_file_path]) }}" class="inline-flex items-center text-sm text-[#3F5189] hover:text-[#2F3F6F]">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            {{ __('Download') }}
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Actions Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Actions') }}</h3>
                </div>
                <div class="p-6 space-y-3">
                    @if(count($this->availableStatuses) > 0)
                        @can('contracts.edit', $contract)
                            <x-ui.button
                                variant="primary"
                                class="w-full justify-center"
                                wire:click="openStatusModal"
                                icon="refresh">
                                {{ __('Change Status') }}
                            </x-ui.button>
                        @endcan
                    @endif

                    @if(in_array($contract->status, ['active', 'completed', 'partially_paid']))
                        @can('contracts.pay', $contract)
                            <x-ui.button
                                variant="success"
                                class="w-full justify-center"
                                wire:click="openPaymentModal"
                                icon="plus">
                                {{ __('Record Payment') }}
                            </x-ui.button>
                        @endcan
                    @endif

                    @if($contract->getRetentionOutstanding() > 0)
                        @can('contracts.pay', $contract)
                            <x-ui.button
                                variant="warning"
                                class="w-full justify-center"
                                wire:click="openRetentionModal"
                                icon="banknotes">
                                {{ __('Release Retention') }}
                            </x-ui.button>
                        @endcan
                    @endif

                    @can('contracts.edit', $contract)
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('contracts.edit', $contract->id) }}"
                            class="w-full justify-center"
                            icon="edit">
                            {{ __('Edit Contract') }}
                        </x-ui.button>
                    @endcan

                    @can('contracts.delete', $contract)
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="delete"
                            wire:confirm="{{ __('Are you sure you want to delete this contract? This action cannot be undone.') }}"
                            icon="trash">
                            {{ __('Delete Contract') }}
                        </x-ui.button>
                    @endcan
                </div>
            </div>

            <!-- Status History Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Status History') }}</h3>
                </div>
                <div class="p-6">
                    @if($contract->statusHistories->count() > 0)
                        <div class="flow-root">
                            <ul class="-mb-8">
                                @foreach($contract->statusHistories->sortByDesc('created_at') as $history)
                                    <li>
                                        <div class="relative pb-8">
                                            @if(!$loop->last)
                                                <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-slate-200 dark:bg-slate-700"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    @php
                                                        $historyStatusColors = [
                                                            'active' => 'bg-green-500',
                                                            'completed' => 'bg-blue-500',
                                                            'partially_paid' => 'bg-yellow-500',
                                                            'paid' => 'bg-emerald-500',
                                                            'cancelled' => 'bg-red-500',
                                                        ];
                                                    @endphp
                                                    <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-slate-800 {{ $historyStatusColors[$history->new_status] ?? 'bg-slate-400' }}">
                                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            @switch($history->new_status)
                                                                @case('active')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                    @break
                                                                @case('completed')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                    @break
                                                                @case('paid')
                                                                @case('partially_paid')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    @break
                                                                @case('cancelled')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                    @break
                                                                @default
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            @endswitch
                                                        </svg>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div>
                                                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                                                            @if($history->old_status)
                                                                {{ $statusLabels[$history->old_status] ?? ucfirst($history->old_status) }}
                                                                &rarr;
                                                            @endif
                                                            {{ $statusLabels[$history->new_status] ?? ucfirst($history->new_status) }}
                                                        </p>
                                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                                            {{ __('by') }} {{ $history->changedBy?->name ?? __('System') }}
                                                        </p>
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ $history->created_at->format('M d, Y H:i') }}
                                                    </div>
                                                    @if($history->reason)
                                                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900 rounded p-2">
                                                            {{ $history->reason }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No status changes recorded.') }}</p>
                    @endif
                </div>
            </div>

            <!-- Payment History Card -->
            @if($contract->payments->count() > 0)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Payment History') }}</h3>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($contract->payments as $payment)
                            <div class="p-4">
                                <div class="flex items-start justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ Number::currency($payment->amount, config('app.currency'), config('app.locale')) }}
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            {{ $payment->payment_date->format('M d, Y') }} &middot; {{ $payment->getPaymentMethodLabel() }}
                                        </p>
                                        @if($payment->is_retention_release)
                                            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400">
                                                {{ __('Retention Release') }}
                                            </span>
                                        @elseif($payment->scheduleItem)
                                            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-400">
                                                {{ $payment->scheduleItem->description }}
                                            </span>
                                        @endif
                                        @if($payment->reference_number)
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ __('Ref:') }} {{ $payment->reference_number }}
                                            </p>
                                        @endif
                                        @if($payment->notes)
                                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                                                {{ $payment->notes }}
                                            </p>
                                        @endif
                                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                            {{ __('by') }} {{ $payment->createdBy?->name ?? __('Unknown') }}
                                        </p>
                                    </div>
                                    @can('contracts.unpay', $contract)
                                        <x-ui.button
                                            variant="danger"
                                            size="sm"
                                            wire:click="deletePayment({{ $payment->id }})"
                                            wire:confirm="{{ __('Are you sure you want to delete this payment?') }}"
                                            icon="trash">
                                        </x-ui.button>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Status Change Modal -->
    @if($showStatusModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeStatusModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Change Contract Status') }}</h2>
                        <button type="button" wire:click="closeStatusModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('New Status') }}</label>
                            <select
                                wire:model="newStatus"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @foreach($this->availableStatuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Reason (Optional)') }}</label>
                            <textarea
                                wire:model="statusReason"
                                rows="3"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Reason for status change...') }}"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeStatusModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="primary" wire:click="changeStatus">
                            {{ __('Update Status') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Retention Release Modal -->
    @if($showRetentionModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeRetentionModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Release Retention') }}</h2>
                        <button type="button" wire:click="closeRetentionModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        {{ __('Retention outstanding: :amount. The amount is capped at this value.', ['amount' => Number::currency($contract->getRetentionOutstanding(), config('app.currency'), config('app.locale'))]) }}
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Amount *') }}</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-500 sm:text-sm">$</span>
                                </div>
                                <input
                                    type="number"
                                    wire:model="retentionAmount"
                                    step="0.01"
                                    min="0.01"
                                    max="{{ $contract->getRetentionOutstanding() }}"
                                    class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            </div>
                            @error('retentionAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Payment Method *') }}</label>
                            <select
                                wire:model="retentionMethod"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="check">{{ __('Check') }}</option>
                                <option value="cash">{{ __('Cash') }}</option>
                                <option value="credit_card">{{ __('Credit Card') }}</option>
                                <option value="debit_card">{{ __('Debit Card') }}</option>
                                <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                                <option value="pix">PIX</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                            @error('retentionMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Payment Date *') }}</label>
                            <input
                                type="date"
                                wire:model="retentionDate"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('retentionDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Reference Number') }}</label>
                            <input
                                type="text"
                                wire:model="retentionReference"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Check #, transaction ID, etc.') }}">
                            @error('retentionReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Notes') }}</label>
                            <textarea
                                wire:model="retentionNotes"
                                rows="2"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Optional notes...') }}"></textarea>
                            @error('retentionNotes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeRetentionModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="warning" wire:click="releaseRetention" icon="banknotes">
                            {{ __('Release Retention') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Payment Modal -->
    @if($showPaymentModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closePaymentModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full {{ count($paymentItems) > 0 ? 'max-w-3xl' : 'max-w-md' }} bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Record Payment') }}</h2>
                        <button type="button" wire:click="closePaymentModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    @php
                        $unscheduledRemaining = $this->hasSchedule ? $this->unscheduledRemaining : 0;
                        $scheduleBlocked = $this->hasSchedule
                            && $this->payableScheduleItems->count() === 0
                            && $this->payableMeasurements->count() === 0
                            && $unscheduledRemaining <= 0;
                    @endphp

                    <div class="space-y-4">
                        @if(!$scheduleBlocked && $this->payableMeasurements->count() > 0)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Measurement') }}</label>
                                <select
                                    wire:model.live="paymentMeasurementId"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="">{{ __('Not a measurement payment') }}</option>
                                    @foreach($this->payableMeasurements as $payableMeasurement)
                                        <option value="{{ $payableMeasurement->id }}">
                                            {{ __('Measurement') }} #{{ $payableMeasurement->measurement_number }}
                                            &middot; {{ $payableMeasurement->period_start->format('d/m/Y') }}—{{ $payableMeasurement->period_end->format('d/m/Y') }}
                                            &middot; {{ __('Net') }} {{ Number::currency($payableMeasurement->getRemainingNet(), config('app.currency'), config('app.locale')) }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Paying a measurement pays its net (gross minus retention) and fills the cost codes from the boletim.') }}</p>
                                @error('paymentMeasurementId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @if($scheduleBlocked)
                            <div class="p-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                                {{ __('This contract is paid through its payment schedule and no installment is approved for payment yet. Approve an installment in the payment schedule first.') }}
                            </div>
                        @elseif(($this->payableScheduleItems->count() > 0 || $unscheduledRemaining > 0) && $paymentMeasurementId === '')
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ $this->hasSchedule && $unscheduledRemaining <= 0 ? __('Installment *') : __('Installment') }}
                                </label>
                                <select
                                    wire:model.live="paymentScheduleItemId"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    @if(!$this->hasSchedule)
                                        <option value="">{{ __('No installment (general payment)') }}</option>
                                    @elseif($unscheduledRemaining > 0)
                                        <option value="">
                                            {{ __('Unscheduled balance') }}
                                            &middot; {{ Number::currency($unscheduledRemaining, config('app.currency'), config('app.locale')) }}
                                        </option>
                                    @else
                                        <option value="">{{ __('Select an installment...') }}</option>
                                    @endif
                                    @foreach($this->payableScheduleItems as $scheduleItem)
                                        <option value="{{ $scheduleItem->id }}">
                                            {{ $scheduleItem->description }}
                                            &middot; {{ __('Balance') }} {{ Number::currency($scheduleItem->getBalance(), config('app.currency'), config('app.locale')) }}
                                            @if($scheduleItem->due_date)
                                                &middot; {{ __('Due Date:') }} {{ $scheduleItem->due_date->format('d/m/Y') }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $this->hasSchedule && $unscheduledRemaining > 0
                                        ? __('Only approved installments are listed. The unscheduled balance is the part of the contract the schedule does not cover.')
                                        : __('Only approved installments are listed.') }}
                                </p>
                                @error('paymentScheduleItemId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @if(!$scheduleBlocked)
                        @if(count($paymentItems) > 0)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Cost Codes') }}</label>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('Enter the new % complete for a code to get a suggested amount (you can adjust it), or type amounts directly. Leave lines empty to record an uncoded payment.') }}</p>
                                @error('paymentItems')
                                    <div class="mb-2 p-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                                        {{ $message }}
                                    </div>
                                @enderror
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Cost Code') }}</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Scheduled') }}</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Paid / %') }}</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-24">{{ __('New %') }}</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-32">{{ __('Amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                            @foreach($paymentItems as $index => $item)
                                                <tr wire:key="payment-item-{{ $item['budget_item_id'] }}">
                                                    <td class="px-3 py-2 text-sm text-slate-900 dark:text-white">{{ $item['code_display'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-right text-slate-700 dark:text-slate-300">
                                                        {{ Number::currency($item['scheduled'], config('app.currency'), config('app.locale')) }}
                                                    </td>
                                                    <td class="px-3 py-2 text-sm text-right text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                        {{ Number::currency($item['prior_paid'], config('app.currency'), config('app.locale')) }}
                                                        @if($item['prior_percent'] !== null)
                                                            · {{ rtrim(rtrim(number_format($item['prior_percent'], 2, '.', ''), '0'), '.') }}%
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input
                                                            type="number"
                                                            step="0.1"
                                                            min="0"
                                                            max="100"
                                                            wire:model.live.debounce.500ms="paymentItems.{{ $index }}.percent"
                                                            placeholder="{{ $item['prior_percent'] !== null ? rtrim(rtrim(number_format($item['prior_percent'], 2, '.', ''), '0'), '.') : '0' }}"
                                                            class="w-20 px-2 py-1.5 text-sm text-right border border-slate-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <div class="relative">
                                                            <span class="absolute left-2 top-1.5 text-slate-500 text-sm">$</span>
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                wire:model.live.debounce.500ms="paymentItems.{{ $index }}.amount"
                                                                placeholder="0.00"
                                                                class="w-28 pl-6 pr-2 py-1.5 text-sm text-right border border-slate-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                                        </div>
                                                    </td>
                                                </tr>
                                                @error('paymentItems.' . $index . '.percent')
                                                    <tr><td colspan="5" class="px-3 pb-1 text-sm text-red-500">{{ $message }}</td></tr>
                                                @enderror
                                                @error('paymentItems.' . $index . '.amount')
                                                    <tr><td colspan="5" class="px-3 pb-1 text-sm text-red-500">{{ $message }}</td></tr>
                                                @enderror
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Amount *') }}</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-500 sm:text-sm">$</span>
                                </div>
                                <input
                                    type="number"
                                    wire:model="paymentAmount"
                                    step="0.01"
                                    min="0.01"
                                    class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="0.00">
                            </div>
                            @error('paymentAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Payment Method *') }}</label>
                            <select
                                wire:model="paymentMethod"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="check">{{ __('Check') }}</option>
                                <option value="cash">{{ __('Cash') }}</option>
                                <option value="credit_card">{{ __('Credit Card') }}</option>
                                <option value="debit_card">{{ __('Debit Card') }}</option>
                                <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                                <option value="pix">PIX</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                            @error('paymentMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Payment Date *') }}</label>
                            <input
                                type="date"
                                wire:model="paymentDate"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('paymentDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Reference Number') }}</label>
                            <input
                                type="text"
                                wire:model="paymentReference"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Check #, transaction ID, etc.') }}">
                            @error('paymentReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Notes') }}</label>
                            <textarea
                                wire:model="paymentNotes"
                                rows="2"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Optional notes...') }}"></textarea>
                            @error('paymentNotes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closePaymentModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        @if(!$scheduleBlocked)
                            <x-ui.button type="button" variant="primary" wire:click="recordPayment">
                                {{ __('Record Payment') }}
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
